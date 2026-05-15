<?php

namespace App\Services\Pracas;

use App\Models\Praca;
use App\Models\PracaImportacao;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class PracaImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    private const COMPARABLE_FIELDS = [
        'nome',
        'id_unidade_negocio',
    ];

    public function __construct(private readonly PracaPlanilhaNormalizer $normalizer) {}

    public function processar(PracaImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => PracaImportacao::STATUS_PROCESSANDO,
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

        $chavesVistas = [];
        $buffer = [];

        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $dadosBrutos = [
                $sheet->getCell('A'.$r)->getValue(),
                $sheet->getCell('B'.$r)->getValue(),
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
                    'nome' => '',
                    'erros' => ['Limite de '.self::MAX_LINHAS_UTEIS.' linhas úteis excedido. As linhas seguintes foram ignoradas.'],
                    'dados' => [],
                ];
                break;
            }

            $rowId++;
            $normalized = $this->normalizer->normalize($dadosBrutos);
            $dados = $normalized['dados'];
            $errosLinha = $normalized['erros'];
            $chave = $this->chaveComposta($dados['nome'], $dados['id_unidade_negocio']);

            if ($chave !== '|0' && isset($chavesVistas[$chave])) {
                $errosLinha[] = "Combinação nome + unidade duplicada na planilha (já aparece na linha {$chavesVistas[$chave]}).";
            } elseif ($chave !== '|0') {
                $chavesVistas[$chave] = $r;
            }

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'nome' => $dados['nome'],
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
            'status' => PracaImportacao::STATUS_CONCLUIDO,
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

    public function marcarFalha(PracaImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de Praças falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => PracaImportacao::STATUS_FALHOU,
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
        $query = Praca::query();

        $query->where(function ($q) use ($buffer) {
            foreach ($buffer as $item) {
                $dados = $item['dados'];
                $q->orWhere(function ($sub) use ($dados) {
                    $sub->where('nome', $dados['nome'])
                        ->where('id_unidade_negocio', $dados['id_unidade_negocio']);
                });
            }
        });

        $existentes = $query->get()->keyBy(
            fn (Praca $praca) => $this->chaveComposta($praca->nome, (int) $praca->id_unidade_negocio),
        );

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $chave = $this->chaveComposta($dados['nome'], $dados['id_unidade_negocio']);

            $pracaExistente = $existentes->get($chave);

            if ($pracaExistente === null) {
                $novas[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'dados' => $dados,
                ];

                continue;
            }

            $diff = $this->diffCampos($pracaExistente, $dados);

            if ($diff === []) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'praca_id' => $pracaExistente->id,
                    'nome' => $pracaExistente->nome,
                    'id_unidade_negocio' => (int) $pracaExistente->id_unidade_negocio,
                ];

                continue;
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'praca_id' => $pracaExistente->id,
                'nome' => $pracaExistente->nome,
                'id_unidade_negocio' => (int) $pracaExistente->id_unidade_negocio,
                'linha' => $linha,
                'dados_atuais' => $this->snapshot($pracaExistente),
                'dados_novos' => $dados,
                'campos_alterados' => $diff,
            ];
        }
    }

    private function chaveComposta(string $nome, int $idUnidade): string
    {
        return TextoCadastro::normalizarMaiusculas($nome).'|'.$idUnidade;
    }

    /**
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $atualizacoes
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function atualizarProgressoSeNecessario(
        PracaImportacao $importacao,
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
        PracaImportacao $importacao,
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
    private function diffCampos(Praca $praca, array $dados): array
    {
        $diff = [];
        $snapshot = $this->snapshot($praca);

        foreach (self::COMPARABLE_FIELDS as $campo) {
            $atual = $snapshot[$campo] ?? null;
            $novo = $dados[$campo] ?? null;

            if ($campo === 'id_unidade_negocio') {
                $a = (int) $atual;
                $b = (int) $novo;
            } else {
                $a = (string) ($atual ?? '');
                $b = (string) ($novo ?? '');
            }

            if ($a !== $b) {
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
    private function snapshot(Praca $praca): array
    {
        return [
            'nome' => $praca->nome,
            'id_unidade_negocio' => (int) $praca->id_unidade_negocio,
        ];
    }
}
