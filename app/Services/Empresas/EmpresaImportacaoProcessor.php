<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use App\Models\EmpresaImportacao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Processa a planilha de Empresas em background.
 *
 * Estratégia:
 *  - Lê a planilha **uma única vez** com PhpSpreadsheet puro, em modo
 *    `setReadDataOnly(true)` e com um {@see ImportacaoReadFilter} limitando
 *    a leitura às colunas A:G e a no máximo {@see self::MAX_LINHAS_ESCANEADAS}.
 *  - Itera linha a linha. Linhas vazias contam para o progresso mas não
 *    geram resultado.
 *  - As linhas válidas vão para um buffer; a cada {@see self::CHUNK_DB}
 *    linhas o buffer é "flushado" — uma única consulta `WHERE IN` confere
 *    quais `id_cigam` já existem no banco, e outra confere colisões de
 *    `cpf_cnpj`.
 *  - O progresso é gravado em {@see EmpresaImportacao} a cada chunk
 *    (e ao final), com `linhas_processadas`, `percentual` e os contadores
 *    parciais (`novas_count`, `atualizacoes_count`, `sem_alteracoes_count`,
 *    `erros_count`), permitindo polling em tempo real pela UI.
 *
 * Esta classe NÃO grava nas tabelas de empresas — apenas monta o resultado
 * e o salva em {@see EmpresaImportacao::$resultado}. A persistência real
 * acontece somente quando o usuário confirma a importação no controller.
 */
class EmpresaImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    private const COMPARABLE_FIELDS = [
        'status',
        'nome',
        'fantasia',
        'cpf_cnpj',
        'unidade_negocio',
        'tipo_pessoa',
    ];

    public function __construct(private readonly EmpresaPlanilhaNormalizer $normalizer) {}

    public function processar(EmpresaImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => EmpresaImportacao::STATUS_PROCESSANDO,
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
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_LINHAS_ESCANEADAS);
        $totalLinhas = max(0, $highestRow - 1);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $novas = [];
        $atualizacoes = [];
        $semAlteracoes = [];
        $erros = [];

        $idsCigamVistos = [];
        $cpfCnpjVistos = [];
        $buffer = [];

        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $dadosBrutos = [
                $sheet->getCell('A'.$r)->getValue(),
                $sheet->getCell('B'.$r)->getValue(),
                $sheet->getCell('C'.$r)->getValue(),
                $sheet->getCell('D'.$r)->getValue(),
                $sheet->getCell('E'.$r)->getValue(),
                $sheet->getCell('F'.$r)->getValue(),
                $sheet->getCell('G'.$r)->getValue(),
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

            if ($idCigam !== '' && isset($idsCigamVistos[$idCigam])) {
                $errosLinha[] = "ID CIGAM duplicado na planilha (já aparece na linha {$idsCigamVistos[$idCigam]}).";
            } elseif ($idCigam !== '') {
                $idsCigamVistos[$idCigam] = $r;
            }

            if ($dados['cpf_cnpj'] !== '' && isset($cpfCnpjVistos[$dados['cpf_cnpj']])) {
                $errosLinha[] = "CPF/CNPJ duplicado na planilha (já aparece na linha {$cpfCnpjVistos[$dados['cpf_cnpj']]}).";
            } elseif ($dados['cpf_cnpj'] !== '') {
                $cpfCnpjVistos[$dados['cpf_cnpj']] = $r;
            }

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

        $resultado = [
            'novas' => $novas,
            'atualizacoes' => $atualizacoes,
            'sem_alteracoes' => $semAlteracoes,
            'erros' => $erros,
        ];

        $importacao->forceFill([
            'status' => EmpresaImportacao::STATUS_CONCLUIDO,
            'finished_at' => now(),
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => 100,
            'novas_count' => count($novas),
            'atualizacoes_count' => count($atualizacoes),
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
            'resultado' => $resultado,
        ])->save();
    }

    public function marcarFalha(EmpresaImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de Empresas falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => EmpresaImportacao::STATUS_FALHOU,
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
        $cpfsNovas = [];

        foreach ($buffer as $item) {
            $idCigam = $item['dados']['id_cigam'];
            if ($idCigam !== '') {
                $idsCigam[] = $idCigam;
            }
        }

        $existentes = Empresa::query()
            ->whereIn('id_cigam', array_values(array_unique($idsCigam)))
            ->get()
            ->keyBy('id_cigam');

        foreach ($buffer as $item) {
            $cpf = $item['dados']['cpf_cnpj'];
            if ($cpf !== '' && ! $existentes->has($item['dados']['id_cigam'])) {
                $cpfsNovas[] = $cpf;
            }
        }

        $colisoesPorCpf = Empresa::query()
            ->whereIn('cpf_cnpj', array_values(array_unique($cpfsNovas)))
            ->get(['id', 'id_cigam', 'cpf_cnpj'])
            ->keyBy('cpf_cnpj');

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $idCigam = $dados['id_cigam'];

            $empresaExistente = $existentes->get($idCigam);

            if ($empresaExistente === null) {
                $colisao = $dados['cpf_cnpj'] !== '' ? $colisoesPorCpf->get($dados['cpf_cnpj']) : null;
                if ($colisao !== null) {
                    $erros[] = [
                        'row_id' => $rowId,
                        'linha' => $linha,
                        'id_cigam' => $idCigam,
                        'erros' => ["CPF/CNPJ já cadastrado em outra empresa (id_cigam={$colisao->id_cigam})."],
                        'dados' => $dados,
                    ];

                    continue;
                }

                $novas[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'dados' => $dados,
                ];

                continue;
            }

            $diff = $this->diffCampos($empresaExistente, $dados);

            if ($diff === []) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'empresa_id' => $empresaExistente->id,
                    'id_cigam' => $idCigam,
                    'nome' => $empresaExistente->nome,
                ];

                continue;
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'empresa_id' => $empresaExistente->id,
                'id_cigam' => $idCigam,
                'linha' => $linha,
                'dados_atuais' => $this->snapshot($empresaExistente),
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
        EmpresaImportacao $importacao,
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
        EmpresaImportacao $importacao,
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
     * @param  array<string,mixed>  $dados
     * @return list<array{campo:string, atual:mixed, novo:mixed}>
     */
    private function diffCampos(Empresa $empresa, array $dados): array
    {
        $diff = [];

        foreach (self::COMPARABLE_FIELDS as $campo) {
            $atual = $empresa->{$campo};
            $novo = $dados[$campo] ?? null;

            if ($campo === 'status') {
                $atual = (bool) $atual;
                $novo = (bool) $novo;
            } elseif ($campo === 'unidade_negocio') {
                $atual = (int) $atual;
                $novo = (int) $novo;
            } else {
                $atual = (string) ($atual ?? '');
                $novo = (string) ($novo ?? '');
            }

            if ($atual !== $novo) {
                $diff[] = [
                    'campo' => $campo,
                    'atual' => $empresa->{$campo},
                    'novo' => $dados[$campo] ?? null,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Empresa $empresa): array
    {
        return [
            'status' => (bool) $empresa->status,
            'id_cigam' => $empresa->id_cigam,
            'nome' => $empresa->nome,
            'fantasia' => $empresa->fantasia,
            'cpf_cnpj' => $empresa->cpf_cnpj,
            'unidade_negocio' => (int) $empresa->unidade_negocio,
            'tipo_pessoa' => $empresa->tipo_pessoa,
        ];
    }
}
