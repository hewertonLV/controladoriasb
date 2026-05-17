<?php

namespace App\Services\Fornecedores;

use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\FornecedorImportacao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class FornecedorImportacaoProcessor
{
    public const HEARTBEAT_CACHE_KEY = 'queue_worker_imports_last_seen_at';

    /**
     * @var array<string, int>|null
     */
    private ?array $idsEstadoPorBusca = null;

    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'id_estado',
        'razao_social',
        'fantasia',
        'cnpj_cpf',
    ];

    public function __construct(private readonly FornecedorPlanilhaNormalizer $normalizer) {}

    public function processar(FornecedorImportacao $importacao): void
    {
        $this->registrarHeartbeat();

        $importacao->forceFill([
            'status' => FornecedorImportacao::STATUS_PROCESSANDO,
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

        $importacao->forceFill([
            'total_linhas' => $totalLinhas,
            'linhas_processadas' => 0,
            'percentual' => $totalLinhas > 0 ? 1 : 0,
        ])->save();

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
                $sheet->getCell('A'.$r)->getValue(),
                $sheet->getCell('B'.$r)->getValue(),
                $sheet->getCell('C'.$r)->getValue(),
                $sheet->getCell('D'.$r)->getValue(),
                $sheet->getCell('E'.$r)->getValue(),
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

            $estadoBusca = $dados['estado_busca'] ?? '';
            unset($dados['estado_busca']);
            if ($estadoBusca !== '' && $errosLinha === []) {
                $idEstado = $this->idsEstadoPorBusca()[$estadoBusca] ?? null;
                if ($idEstado === null) {
                    $errosLinha[] = "Estado \"{$estadoBusca}\" não cadastrado. Utilize a abreviação ou o nome do estado.";
                } else {
                    $dados['id_estado'] = (int) $idEstado;
                }
            }

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
            'status' => FornecedorImportacao::STATUS_CONCLUIDO,
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

    public function marcarFalha(FornecedorImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de Fornecedores falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => FornecedorImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => $this->mensagemAmigavel($e),
        ])->save();
    }

    /**
     * @return array<string, int>
     */
    private function idsEstadoPorBusca(): array
    {
        if ($this->idsEstadoPorBusca !== null) {
            return $this->idsEstadoPorBusca;
        }

        $map = [];

        foreach (Estado::query()->get(['id', 'nome', 'abreviacao']) as $estado) {
            $abreviacao = Estado::normalizarChaveBusca($estado->abreviacao);
            if ($abreviacao !== '') {
                $map[$abreviacao] = (int) $estado->id;
            }
        }

        foreach (Estado::query()->get(['id', 'nome', 'abreviacao']) as $estado) {
            $nome = Estado::normalizarChaveBusca($estado->nome);
            if ($nome !== '' && ! isset($map[$nome])) {
                $map[$nome] = (int) $estado->id;
            }
        }

        return $this->idsEstadoPorBusca = $map;
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
        $cpfsNovos = [];

        foreach ($buffer as $item) {
            $idCigam = $item['dados']['id_cigam'];
            if ($idCigam !== '') {
                $idsCigam[] = $idCigam;
            }
        }

        $existentes = Fornecedor::query()
            ->whereIn('id_cigam', array_values(array_unique($idsCigam)))
            ->with('estado:id,nome,abreviacao')
            ->get()
            ->keyBy('id_cigam');

        foreach ($buffer as $item) {
            $cpf = $item['dados']['cnpj_cpf'];
            if ($cpf !== '' && ! $existentes->has($item['dados']['id_cigam'])) {
                $cpfsNovos[] = $cpf;
            }
        }

        $colisoesPorCpf = Fornecedor::query()
            ->whereIn('cnpj_cpf', array_values(array_unique($cpfsNovos)))
            ->get(['id', 'id_cigam', 'cnpj_cpf'])
            ->keyBy('cnpj_cpf');

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $idCigam = $dados['id_cigam'];

            $fornecedorExistente = $existentes->get($idCigam);

            if ($fornecedorExistente === null) {
                $colisao = $dados['cnpj_cpf'] !== '' ? $colisoesPorCpf->get($dados['cnpj_cpf']) : null;
                if ($colisao !== null) {
                    $erros[] = [
                        'row_id' => $rowId,
                        'linha' => $linha,
                        'id_cigam' => $idCigam,
                        'erros' => ["CPF/CNPJ já cadastrado em outro fornecedor (id_cigam={$colisao->id_cigam})."],
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

            $diff = $this->diffCampos($fornecedorExistente, $dados);

            if ($diff === []) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'fornecedor_id' => $fornecedorExistente->id,
                    'id_cigam' => $idCigam,
                    'razao_social' => $fornecedorExistente->razao_social,
                ];

                continue;
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'fornecedor_id' => $fornecedorExistente->id,
                'id_cigam' => $idCigam,
                'linha' => $linha,
                'dados_atuais' => $this->snapshot($fornecedorExistente),
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
        FornecedorImportacao $importacao,
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
        FornecedorImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
        array $atualizacoes,
        array $semAlteracoes,
        array $erros,
    ): void {
        $this->registrarHeartbeat();

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

    private function registrarHeartbeat(): void
    {
        Cache::put(self::HEARTBEAT_CACHE_KEY, now()->toIso8601String(), 120);
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
    private function diffCampos(Fornecedor $fornecedor, array $dados): array
    {
        $diff = [];

        foreach (self::COMPARABLE_FIELDS as $campo) {
            if ($campo === 'id_estado') {
                $atual = (int) $fornecedor->id_estado;
                $novo = (int) ($dados[$campo] ?? 0);
                if ($atual !== $novo) {
                    $diff[] = [
                        'campo' => $campo,
                        'atual' => $fornecedor->estado?->nome ?? $atual,
                        'novo' => Estado::query()->whereKey($novo)->value('nome') ?? $novo,
                    ];
                }

                continue;
            }

            $atual = (string) ($fornecedor->{$campo} ?? '');
            $novo = (string) ($dados[$campo] ?? '');

            if ($campo === 'fantasia') {
                $atual = $atual === '' ? '' : $atual;
                $novo = $novo === '' ? '' : $novo;
            }

            if ($atual !== $novo) {
                $diff[] = [
                    'campo' => $campo,
                    'atual' => $fornecedor->{$campo},
                    'novo' => $dados[$campo] ?? null,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Fornecedor $fornecedor): array
    {
        return [
            'id_cigam' => $fornecedor->id_cigam,
            'id_estado' => (int) $fornecedor->id_estado,
            'razao_social' => $fornecedor->razao_social,
            'fantasia' => $fornecedor->fantasia,
            'cnpj_cpf' => $fornecedor->cnpj_cpf,
        ];
    }
}
