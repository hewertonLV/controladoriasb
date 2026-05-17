<?php

namespace App\Services\Frutas;

use App\Enums\FrutaUnidadeMedicao;
use App\Models\Fruta;
use App\Models\FrutaImportacao;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FrutaImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'nome',
        'unidade_medicao',
        'kg_por_unidade_medicao',
        'icms_ex_compra',
        'icms_na_compra',
        'um_icms',
        'icms_venda',
    ];

    private const HEADER_ALIASES = [
        'id_cigam' => ['IDCIGAM', 'ID', 'CODIGO', 'CODIGOMATERIAL', 'CODMATERIAL', 'MATERIAL'],
        'nome' => ['NOME', 'DESCRICAO', 'DESCRICAOMAT', 'DESCRICAOMATERIAL'],
        'unidade_medicao' => ['UNIDADE', 'UNIDADEMEDICAO', 'UNIDMEDIDA', 'UMMEDIDA', 'UNMEDIDA'],
        'kg_por_unidade_medicao' => ['KG', 'KGPORUNIDADE', 'KGPORUNIDADEMEDICAO', 'PESO', 'PESOMAT'],
        'icms_ex_compra' => ['ICMSEXCOMPRA', 'ICMSEXTERNOCOMPRA', 'ICMSEXTERNONACOMPRA'],
        'icms_na_compra' => ['ICMSNACOMPRA', 'ICMSNACIONALCOMPRA', 'ICMSNACIONALNACOMPRA'],
        'um_icms' => ['UMICMS', 'UNIDADEICMS'],
        'icms_venda' => ['ICMSVENDA', 'ICMSVENDAPERCENTUAL', 'ICMSVENDA%'],
    ];

    public function __construct(private readonly FrutaPlanilhaNormalizer $normalizer) {}

    public function processar(FrutaImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => FrutaImportacao::STATUS_PROCESSANDO,
            'started_at' => $importacao->started_at ?? now(),
            'erro_mensagem' => null,
        ])->save();

        $absoluto = Storage::disk('local')->path($importacao->arquivo_path);

        if (! is_file($absoluto)) {
            throw new \RuntimeException("Arquivo de importação não encontrado: {$importacao->arquivo_path}");
        }

        $reader = IOFactory::createReaderForFile($absoluto);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter(new ImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $reader->load($absoluto);
        $sheet = $this->worksheetComLayoutDeFrutas($spreadsheet);
        $headers = $this->headers($sheet);

        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_LINHAS_ESCANEADAS);
        $totalLinhas = max(0, $highestRow - 1);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $novas = [];
        $atualizacoes = [];
        $semAlteracoes = [];
        $erros = [];

        $idsCigamVistos = [];
        $buffer = [];

        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $dadosBrutos = [
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'id_cigam', 'A'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'nome', 'B'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'unidade_medicao', 'C'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'kg_por_unidade_medicao', 'D'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'icms_ex_compra', 'E'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'icms_na_compra', 'F'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'um_icms', 'G'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, 'icms_venda', 'H'),
            ];

            $linhasProcessadas++;

            if ($this->linhaVazia($dadosBrutos)) {
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );

                continue;
            }

            $linhasUteis++;

            if ($linhasUteis > self::MAX_LINHAS_UTEIS) {
                $erros[] = [
                    'row_id' => ++$rowId,
                    'linha' => $r,
                    'id_cigam' => '',
                    'erros' => ['Limite de '.self::MAX_LINHAS_UTEIS.' linhas úteis excedido. As linhas seguintes foram ignoradas.'],
                    'dados' => [],
                ];
                break;
            }

            $rowId++;
            $normalized = $this->normalizer->normalize($dadosBrutos);
            $dados = $normalized['dados'];
            $errosLinha = $normalized['erros'];
            $idCigam = $dados['id_cigam'];

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'id_cigam' => $idCigam,
                    'erros' => $errosLinha,
                    'dados' => $dados,
                ];
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );

                continue;
            }

            if ($idCigam !== '' && isset($idsCigamVistos[$idCigam])) {
                if ($this->dadosComparaveisIguais($idsCigamVistos[$idCigam]['dados'], $dados)) {
                    $this->atualizarProgressoSeNecessario(
                        $importacao,
                        $linhasProcessadas,
                        $totalLinhas,
                        $novas,
                        $atualizacoes,
                        $semAlteracoes,
                        $erros,
                    );

                    continue;
                }

                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'id_cigam' => $idCigam,
                    'erros' => ["ID CIGAM duplicado na planilha (já aparece na linha {$idsCigamVistos[$idCigam]['linha']})."],
                    'dados' => $dados,
                ];
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );

                continue;
            }

            if ($idCigam !== '') {
                $idsCigamVistos[$idCigam] = [
                    'linha' => $r,
                    'dados' => $dados,
                ];
            }

            $buffer[] = [
                'row_id' => $rowId,
                'linha' => $r,
                'dados' => $dados,
            ];

            if (count($buffer) >= self::CHUNK_DB) {
                $this->flushBuffer($buffer, $novas, $atualizacoes, $semAlteracoes, $erros);
                $buffer = [];
                $this->salvarProgresso(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );
            } else {
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $atualizacoes,
                    $semAlteracoes,
                    $erros,
                );
            }
        }

        if ($buffer !== []) {
            $this->flushBuffer($buffer, $novas, $atualizacoes, $semAlteracoes, $erros);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader, $sheet);
        gc_collect_cycles();

        $importacao->forceFill([
            'status' => FrutaImportacao::STATUS_CONCLUIDO,
            'finished_at' => now(),
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => 100,
            'novas_count' => count($novas),
            'atualizacoes_count' => count($atualizacoes),
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
            'resultado' => [
                'novas' => $novas,
                'atualizacoes' => $atualizacoes,
                'sem_alteracoes' => $semAlteracoes,
                'erros' => $erros,
            ],
        ])->save();
    }

    public function marcarFalha(FrutaImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de Frutas falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => FrutaImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => $this->mensagemAmigavel($e),
        ])->save();
    }

    private function mensagemAmigavel(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Allowed memory size')) {
            return 'A planilha excedeu o limite de memória. Remova linhas vazias/formatação extra, salve novamente como .xlsx limpo e tente outra vez.';
        }

        if (str_contains($msg, 'Maximum execution time')) {
            return 'O processamento excedeu o tempo limite. Configure o worker de fila com `--timeout=900` e verifique se a planilha tem milhares de linhas vazias.';
        }

        return 'Falha ao processar a planilha: '.$msg;
    }

    /**
     * @param  list<array{row_id:int, linha:int, dados:array<string,mixed>}>  $buffer
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $atualizacoes
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function flushBuffer(array $buffer, array &$novas, array &$atualizacoes, array &$semAlteracoes, array &$erros): void
    {
        $idsCigam = [];

        foreach ($buffer as $item) {
            $idCigam = $item['dados']['id_cigam'];
            if ($idCigam !== '') {
                $idsCigam[] = $idCigam;
            }
        }

        $existentes = Fruta::query()
            ->whereIn('id_cigam', array_values(array_unique($idsCigam)))
            ->get()
            ->keyBy('id_cigam');

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $idCigam = $dados['id_cigam'];

            $frutaExistente = $existentes->get($idCigam);

            if ($frutaExistente === null) {
                $novas[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'dados' => $dados,
                ];

                continue;
            }

            $diff = $this->diffCampos($frutaExistente, $dados);

            if ($diff === []) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'fruta_id' => $frutaExistente->id,
                    'id_cigam' => $idCigam,
                    'nome' => $frutaExistente->nome,
                ];

                continue;
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'fruta_id' => $frutaExistente->id,
                'id_cigam' => $idCigam,
                'linha' => $linha,
                'dados_atuais' => $this->snapshot($frutaExistente),
                'dados_novos' => $dados,
                'campos_alterados' => $diff,
            ];
        }
    }

    /**
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $atualizacoes
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function atualizarProgressoSeNecessario(
        FrutaImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
        array $atualizacoes,
        array $semAlteracoes,
        array $erros,
    ): void {
        if ($linhasProcessadas % self::PROGRESSO_A_CADA_N_LINHAS !== 0) {
            return;
        }

        $this->salvarProgresso($importacao, $linhasProcessadas, $totalLinhas, $novas, $atualizacoes, $semAlteracoes, $erros);
    }

    /**
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $atualizacoes
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function salvarProgresso(
        FrutaImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
        array $atualizacoes,
        array $semAlteracoes,
        array $erros,
    ): void {
        $percentual = $totalLinhas > 0
            ? (int) min(99, floor($linhasProcessadas * 100 / $totalLinhas))
            : 0;

        $importacao->forceFill([
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => $percentual,
            'novas_count' => count($novas),
            'atualizacoes_count' => count($atualizacoes),
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
        ])->save();
    }

    /**
     * @param  array<int,mixed>  $row
     */
    private function linhaVazia(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $dadosVistos
     * @param  array<string,mixed>  $dadosAtuais
     */
    private function dadosComparaveisIguais(array $dadosVistos, array $dadosAtuais): bool
    {
        foreach (self::COMPARABLE_FIELDS as $field) {
            if ((string) ($dadosVistos[$field] ?? '') !== (string) ($dadosAtuais[$field] ?? '')) {
                return false;
            }
        }

        return true;
    }

    private function worksheetComLayoutDeFrutas(Spreadsheet $spreadsheet): Worksheet
    {
        $melhorSheet = $spreadsheet->getActiveSheet();
        $melhorScore = -1;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $headers = $this->headers($sheet);

            if (! $this->possuiHeadersObrigatorios($headers)) {
                continue;
            }

            $score = $this->pontuarWorksheet($sheet, $headers);
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhorSheet = $sheet;
            }
        }

        return $melhorSheet;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Worksheet $sheet): array
    {
        $headers = [];
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($index = 1; $index <= $highestColumnIndex; $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $header = $this->normalizarHeader($sheet->getCell($column.'1')->getValue());

            if ($header !== '') {
                $headers[$header] = $column;
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function valorPorHeaderOuCelula(Worksheet $sheet, array $headers, int $row, string $field, string $fallbackColumn): mixed
    {
        $column = $this->colunaPorAliases($headers, $field);

        return $sheet->getCell(($column ?? $fallbackColumn).$row)->getValue();
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function colunaPorAliases(array $headers, string $field): ?string
    {
        foreach (self::HEADER_ALIASES[$field] ?? [] as $alias) {
            $alias = $this->normalizarHeader($alias);
            if (isset($headers[$alias])) {
                return $headers[$alias];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function possuiHeadersObrigatorios(array $headers): bool
    {
        return $this->colunaPorAliases($headers, 'id_cigam') !== null
            && $this->colunaPorAliases($headers, 'nome') !== null
            && $this->colunaPorAliases($headers, 'unidade_medicao') !== null
            && $this->colunaPorAliases($headers, 'kg_por_unidade_medicao') !== null;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function pontuarWorksheet(Worksheet $sheet, array $headers): int
    {
        $colUnidade = $this->colunaPorAliases($headers, 'unidade_medicao');
        $colKg = $this->colunaPorAliases($headers, 'kg_por_unidade_medicao');
        $score = 0;
        $lastRow = min((int) $sheet->getHighestDataRow(), 25);

        for ($row = 2; $row <= $lastRow; $row++) {
            $unidadeMedicao = $colUnidade === null
                ? ''
                : FrutaPlanilhaNormalizer::normalizarUnidadeMedicao($sheet->getCell($colUnidade.$row)->getValue());

            if (in_array($unidadeMedicao, FrutaUnidadeMedicao::values(), true)) {
                $score += 2;
            }

            if ($colKg !== null && trim((string) $sheet->getCell($colKg.$row)->getValue()) !== '') {
                $score++;
            }
        }

        return $score;
    }

    private function normalizarHeader(mixed $value): string
    {
        $texto = TextoCadastro::removerAcentos((string) $value);

        return mb_strtoupper(preg_replace('/[^A-Za-z0-9%]/', '', $texto) ?? '', 'UTF-8');
    }

    /**
     * @param  array<string,mixed>  $dados
     * @return list<array{campo:string, atual:mixed, novo:mixed}>
     */
    private function diffCampos(Fruta $fruta, array $dados): array
    {
        $diff = [];
        $snapshot = $this->snapshot($fruta);

        foreach (self::COMPARABLE_FIELDS as $campo) {
            if (in_array($campo, ['kg_por_unidade_medicao', 'icms_ex_compra', 'icms_na_compra', 'icms_venda'], true)) {
                $atual = number_format(max(0, (float) ($snapshot[$campo] ?? 0)), 2, '.', '');
                $novo = number_format(max(0, (float) ($dados[$campo] ?? 0)), 2, '.', '');
            } else {
                $atual = (string) ($snapshot[$campo] ?? '');
                $novo = (string) ($dados[$campo] ?? '');
            }

            if ($atual !== $novo) {
                $diff[] = [
                    'campo' => $campo,
                    'atual' => $snapshot[$campo],
                    'novo' => $dados[$campo] ?? null,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Fruta $fruta): array
    {
        return [
            'id_cigam' => $fruta->id_cigam,
            'nome' => $fruta->nome,
            'unidade_medicao' => $fruta->unidade_medicao,
            'kg_por_unidade_medicao' => number_format(max(0, (float) $fruta->kg_por_unidade_medicao), 2, '.', ''),
            'icms_ex_compra' => number_format(max(0, (float) $fruta->icms_ex_compra), 2, '.', ''),
            'icms_na_compra' => number_format(max(0, (float) $fruta->icms_na_compra), 2, '.', ''),
            'um_icms' => (string) $fruta->um_icms,
            'icms_venda' => number_format(max(0, (float) $fruta->icms_venda), 2, '.', ''),
        ];
    }
}
