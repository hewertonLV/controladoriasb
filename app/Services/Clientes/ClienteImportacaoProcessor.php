<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\ClienteImportacao;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClienteImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    private const COMPARABLE_FIELDS = [
        'razao_social',
        'fantasia',
        'cnpj_cpf',
        'id_unidade_negocio',
        'id_praca',
        'grupo_id',
        'desconto_nf',
    ];

    public function __construct(private readonly ClientePlanilhaNormalizer $normalizer) {}

    public function processar(ClienteImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => ClienteImportacao::STATUS_PROCESSANDO,
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
        $headers = $this->headers($sheet);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $novas = [];
        $atualizacoes = [];
        $semAlteracoes = [];
        $erros = [];

        $idsCigamVistos = [];
        $cnpjCpfVistos = [];
        $buffer = [];

        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $dadosBrutos = [
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['IDCIGAM', 'ID'], 'A'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['RAZAOSOCIAL', 'RAZAO', 'CLIENTE'], 'B'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['CPFCNPJ', 'CNPJCPF', 'CNPJ', 'CPF'], 'C'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['UNIDADENEGOCIO', 'IDUNIDADENEGOCIO', 'UN'], 'D'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['DESCONTONF', 'DESCNF'], 'E'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['PRACA', 'PRAÇA'], 'F'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['GRUPO'], 'G'),
                $this->valorPorHeaderOuCelula($sheet, $headers, $r, ['FANTASIA', 'NOMEFANTASIA', 'FANTASIACLIENTE'], null),
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

            if ($dados['cnpj_cpf'] !== '' && isset($cnpjCpfVistos[$dados['cnpj_cpf']])) {
                $errosLinha[] = "CPF/CNPJ duplicado na planilha (já aparece na linha {$cnpjCpfVistos[$dados['cnpj_cpf']]}).";
            } elseif ($dados['cnpj_cpf'] !== '') {
                $cnpjCpfVistos[$dados['cnpj_cpf']] = $r;
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

        $importacao->forceFill([
            'status' => ClienteImportacao::STATUS_CONCLUIDO,
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

    public function marcarFalha(ClienteImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de Clientes falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => ClienteImportacao::STATUS_FALHOU,
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
        $cnpjsNovos = [];

        foreach ($buffer as $item) {
            $idCigam = $item['dados']['id_cigam'];
            if ($idCigam !== '') {
                $idsCigam[] = $idCigam;
            }
        }

        $existentes = Cliente::query()
            ->whereIn('id_cigam', array_values(array_unique($idsCigam)))
            ->get()
            ->keyBy('id_cigam');

        foreach ($buffer as $item) {
            $cnpj = $item['dados']['cnpj_cpf'];
            if ($cnpj !== '' && ! $existentes->has($item['dados']['id_cigam'])) {
                $cnpjsNovos[] = $cnpj;
            }
        }

        $colisoesPorCnpj = Cliente::query()
            ->whereIn('cnpj_cpf', array_values(array_unique($cnpjsNovos)))
            ->get(['id', 'id_cigam', 'cnpj_cpf'])
            ->keyBy('cnpj_cpf');

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $idCigam = $dados['id_cigam'];

            $clienteExistente = $existentes->get($idCigam);

            if ($clienteExistente === null) {
                $colisao = $dados['cnpj_cpf'] !== '' ? $colisoesPorCnpj->get($dados['cnpj_cpf']) : null;
                if ($colisao !== null) {
                    $erros[] = [
                        'row_id' => $rowId,
                        'linha' => $linha,
                        'id_cigam' => $idCigam,
                        'erros' => ["CPF/CNPJ já cadastrado em outro cliente (id_cigam={$colisao->id_cigam})."],
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

            $diff = $this->diffCampos($clienteExistente, $dados);

            if ($diff === []) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'cliente_id' => $clienteExistente->id,
                    'id_cigam' => $idCigam,
                    'razao_social' => $clienteExistente->razao_social,
                ];

                continue;
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'cliente_id' => $clienteExistente->id,
                'id_cigam' => $idCigam,
                'linha' => $linha,
                'dados_atuais' => $this->snapshot($clienteExistente),
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
        ClienteImportacao $importacao,
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
        ClienteImportacao $importacao,
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
    private function diffCampos(Cliente $cliente, array $dados): array
    {
        $diff = [];

        foreach (self::COMPARABLE_FIELDS as $campo) {
            if (in_array($campo, ['id_unidade_negocio', 'id_praca'], true)) {
                $atual = (int) $cliente->{$campo};
                $novo = (int) ($dados[$campo] ?? 0);
            } elseif ($campo === 'grupo_id') {
                $atual = $cliente->grupo_id === null ? null : (int) $cliente->grupo_id;
                $novo = ($dados[$campo] ?? null) === null ? null : (int) $dados[$campo];
            } elseif ($campo === 'desconto_nf') {
                $atual = number_format((float) $cliente->{$campo}, 2, '.', '');
                $novo = number_format((float) ($dados[$campo] ?? 0), 2, '.', '');
            } else {
                $atual = (string) ($cliente->{$campo} ?? '');
                $novo = (string) ($dados[$campo] ?? '');
            }

            if ($atual !== $novo) {
                $diff[] = [
                    'campo' => $campo,
                    'atual' => $cliente->{$campo},
                    'novo' => $dados[$campo] ?? null,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Cliente $cliente): array
    {
        return [
            'id_cigam' => $cliente->id_cigam,
            'razao_social' => $cliente->razao_social,
            'fantasia' => $cliente->fantasia,
            'cnpj_cpf' => $cliente->cnpj_cpf,
            'id_unidade_negocio' => (int) $cliente->id_unidade_negocio,
            'id_praca' => (int) $cliente->id_praca,
            'grupo_id' => $cliente->grupo_id !== null ? (int) $cliente->grupo_id : null,
            'desconto_nf' => (string) $cliente->desconto_nf,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(Worksheet $sheet): array
    {
        $headers = [];
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

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
     * @param  list<string>  $aliases
     */
    private function valorPorHeaderOuCelula(
        Worksheet $sheet,
        array $headers,
        int $row,
        array $aliases,
        ?string $fallbackColumn,
    ): mixed {
        foreach ($aliases as $alias) {
            $normalized = $this->normalizarHeader($alias);
            if (isset($headers[$normalized])) {
                return $sheet->getCell($headers[$normalized].$row)->getValue();
            }
        }

        return $fallbackColumn === null ? null : $sheet->getCell($fallbackColumn.$row)->getValue();
    }

    private function normalizarHeader(mixed $value): string
    {
        $texto = TextoCadastro::removerAcentos((string) $value);

        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $texto) ?? '');
    }
}
