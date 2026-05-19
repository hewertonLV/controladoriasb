<?php

namespace App\Services\Estados;

use App\Models\Estado;
use App\Models\EstadoImportacao;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EstadoImportacaoProcessor
{
    public const HEARTBEAT_CACHE_KEY = 'queue_worker_estados_importacao_last_seen_at';

    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    /**
     * @var list<string>
     */
    private const COMPARABLE_FIELDS = [
        'id_cigam',
        'nome',
        'abreviacao',
        'descricao',
    ];

    public function __construct(private readonly EstadoPlanilhaNormalizer $normalizer) {}

    public function processar(EstadoImportacao $importacao): void
    {
        $this->registrarHeartbeatWorker();

        $importacao->forceFill([
            'status' => EstadoImportacao::STATUS_PROCESSANDO,
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
        $siglasVistas = [];
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
                    'abreviacao' => '',
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
            $abreviacao = $dados['abreviacao'];

            if ($idCigam !== '' && isset($idsCigamVistos[$idCigam])) {
                $errosLinha[] = "ID CIGAM duplicado na planilha (já aparece na linha {$idsCigamVistos[$idCigam]}).";
            } elseif ($idCigam !== '') {
                $idsCigamVistos[$idCigam] = $r;
            }

            if ($abreviacao !== '' && isset($siglasVistas[$abreviacao])) {
                $errosLinha[] = "Abreviação duplicada na planilha (já aparece na linha {$siglasVistas[$abreviacao]}).";
            } elseif ($abreviacao !== '') {
                $siglasVistas[$abreviacao] = $r;
            }

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'id_cigam' => $idCigam,
                    'abreviacao' => $abreviacao,
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
            'status' => EstadoImportacao::STATUS_CONCLUIDO,
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

    public function marcarFalha(EstadoImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de Estados falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => EstadoImportacao::STATUS_FALHOU,
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
        $siglas = [];

        foreach ($buffer as $item) {
            $idCigam = $item['dados']['id_cigam'];
            if ($idCigam !== '') {
                $idsCigam[] = $idCigam;
            }

            $sigla = $item['dados']['abreviacao'];
            if ($sigla !== '') {
                $siglas[] = $sigla;
            }
        }

        $existentesPorCigam = Estado::withTrashed()
            ->whereIn('id_cigam', array_values(array_unique($idsCigam)))
            ->get()
            ->keyBy('id_cigam');

        $existentesPorSigla = Estado::withTrashed()
            ->whereIn('abreviacao', array_values(array_unique($siglas)))
            ->get()
            ->keyBy('abreviacao');

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $idCigam = $dados['id_cigam'];
            $abreviacao = $dados['abreviacao'];

            $estadoExistente = $existentesPorCigam->get($idCigam);

            if ($estadoExistente === null) {
                $conflitoSigla = $existentesPorSigla->get($abreviacao);
                if ($conflitoSigla !== null) {
                    $erros[] = [
                        'row_id' => $rowId,
                        'linha' => $linha,
                        'id_cigam' => $idCigam,
                        'abreviacao' => $abreviacao,
                        'erros' => [
                            "A abreviação {$abreviacao} já pertence ao estado {$conflitoSigla->nome} (ID CIGAM {$conflitoSigla->id_cigam}).",
                        ],
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

            $diff = $this->diffCampos($estadoExistente, $dados);

            if ($diff !== []) {
                $novaSigla = $dados['abreviacao'];
                $conflitoSigla = $existentesPorSigla->get($novaSigla);
                if ($conflitoSigla !== null && $conflitoSigla->id !== $estadoExistente->id) {
                    $erros[] = [
                        'row_id' => $rowId,
                        'linha' => $linha,
                        'id_cigam' => $idCigam,
                        'abreviacao' => $abreviacao,
                        'erros' => [
                            "A abreviação {$novaSigla} já pertence ao estado {$conflitoSigla->nome} (ID CIGAM {$conflitoSigla->id_cigam}).",
                        ],
                        'dados' => $dados,
                    ];

                    continue;
                }
            }

            if ($diff === []) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'estado_id' => $estadoExistente->id,
                    'id_cigam' => $idCigam,
                    'abreviacao' => $abreviacao,
                    'nome' => $estadoExistente->nome,
                ];

                continue;
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'estado_id' => $estadoExistente->id,
                'id_cigam' => $idCigam,
                'abreviacao' => $abreviacao,
                'nome' => $estadoExistente->nome,
                'linha' => $linha,
                'dados_atuais' => $this->snapshot($estadoExistente),
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
        EstadoImportacao $importacao,
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
        EstadoImportacao $importacao,
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
    private function diffCampos(Estado $estado, array $dados): array
    {
        $diff = [];

        foreach (self::COMPARABLE_FIELDS as $campo) {
            $atual = $estado->{$campo};
            $novo = $dados[$campo] ?? null;

            if ($campo === 'descricao') {
                $atualNorm = $atual === null || $atual === '' ? null : (string) $atual;
                $novoNorm = $novo === null || $novo === '' ? null : (string) $novo;
                if ($atualNorm !== $novoNorm) {
                    $diff[] = [
                        'campo' => $campo,
                        'atual' => $atualNorm,
                        'novo' => $novoNorm,
                    ];
                }

                continue;
            }

            if ((string) $atual !== (string) $novo) {
                $diff[] = [
                    'campo' => $campo,
                    'atual' => $atual,
                    'novo' => $novo,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Estado $estado): array
    {
        return [
            'id_cigam' => $estado->id_cigam,
            'nome' => $estado->nome,
            'abreviacao' => $estado->abreviacao,
            'descricao' => $estado->descricao,
        ];
    }

    private function registrarHeartbeatWorker(): void
    {
        Cache::put(self::HEARTBEAT_CACHE_KEY, now()->toIso8601String(), 120);
    }
}
