<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoCarteiraImportacao;
use App\Models\Cliente;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CaptacaoCarteiraImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    public function __construct(
        private readonly CaptacaoCarteiraImportacaoPlanilhaNormalizer $normalizer,
    ) {}

    public function processar(CaptacaoCarteiraImportacao $importacao): void
    {
        $importacao->loadMissing('carteira.unidadeFaturamento');

        $importacao->forceFill([
            'status' => CaptacaoCarteiraImportacao::STATUS_PROCESSANDO,
            'started_at' => $importacao->started_at ?? now(),
            'erro_mensagem' => null,
        ])->save();

        $absoluto = Storage::disk('local')->path($importacao->arquivo_path);

        if (! is_file($absoluto)) {
            throw new \RuntimeException("Arquivo de importação não encontrado: {$importacao->arquivo_path}");
        }

        $carteira = $importacao->carteira;
        if ($carteira === null) {
            throw new \RuntimeException('Carteira da importação não encontrada.');
        }

        $faturamentoId = (int) $carteira->id_unidade_negocio_faturamento;
        $carteiraId = (int) $carteira->id;
        $clienteIndex = $this->buildClienteIndex($faturamentoId);

        $reader = IOFactory::createReaderForFile($absoluto);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter(new CaptacaoCarteiraImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $reader->load($absoluto);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_LINHAS_ESCANEADAS);
        $totalLinhas = max(0, $highestRow - 1);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $novas = [];
        $semAlteracoes = [];
        $erros = [];

        $codigosVistos = [];
        $buffer = [];

        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $dadosBrutos = [$sheet->getCell('A'.$r)->getValue()];

            $linhasProcessadas++;

            if ($this->linhaVazia($dadosBrutos)) {
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
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
                    'codigo' => '',
                    'erros' => ['Limite de '.self::MAX_LINHAS_UTEIS.' linhas úteis excedido. As linhas seguintes foram ignoradas.'],
                ];
                break;
            }

            $rowId++;
            $normalized = $this->normalizer->normalize($dadosBrutos);
            $dados = $normalized['dados'];
            $errosLinha = $normalized['erros'];

            $codigo = $dados['id_cigam_cliente'];

            if ($codigo !== '') {
                if (isset($codigosVistos[$codigo])) {
                    $errosLinha[] = "Cliente duplicado na planilha (já aparece na linha {$codigosVistos[$codigo]}).";
                } else {
                    $codigosVistos[$codigo] = $r;
                }
            }

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'codigo' => $dados['codigo'],
                    'erros' => $errosLinha,
                ];
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
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
                $this->flushBuffer($buffer, $clienteIndex, $carteiraId, $faturamentoId, $novas, $semAlteracoes, $erros);
                $buffer = [];
                $this->salvarProgresso(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $semAlteracoes,
                    $erros,
                );
            } else {
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $semAlteracoes,
                    $erros,
                );
            }
        }

        if ($buffer !== []) {
            $this->flushBuffer($buffer, $clienteIndex, $carteiraId, $faturamentoId, $novas, $semAlteracoes, $erros);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader, $sheet);
        gc_collect_cycles();

        $importacao->forceFill([
            'status' => CaptacaoCarteiraImportacao::STATUS_CONCLUIDO,
            'finished_at' => now(),
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => 100,
            'novas_count' => count($novas),
            'atualizacoes_count' => 0,
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
            'resultado' => [
                'novas' => $novas,
                'atualizacoes' => [],
                'sem_alteracoes' => $semAlteracoes,
                'erros' => $erros,
            ],
        ])->save();
    }

    public function marcarFalha(CaptacaoCarteiraImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de lojas da carteira falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => CaptacaoCarteiraImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => $this->mensagemAmigavel($e),
        ])->save();
    }

    /**
     * @return array<string, list<Cliente>>
     */
    private function buildClienteIndex(int $faturamentoId): array
    {
        $index = [];

        $clientes = Cliente::query()
            ->where('id_unidade_negocio', $faturamentoId)
            ->get(['id', 'id_cigam', 'id_captacao_carteira', 'id_unidade_negocio', 'razao_social', 'fantasia']);

        foreach ($clientes as $cliente) {
            $chave = TextoCadastro::normalizarIdCigam((string) $cliente->id_cigam);
            if ($chave === '') {
                continue;
            }

            $index[$chave][] = $cliente;
        }

        return $index;
    }

    /**
     * @param  array<string, list<Cliente>>  $clienteIndex
     * @param  list<array{row_id:int, linha:int, dados:array<string,string>}>  $buffer
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function flushBuffer(
        array $buffer,
        array $clienteIndex,
        int $carteiraId,
        int $faturamentoId,
        array &$novas,
        array &$semAlteracoes,
        array &$erros,
    ): void {
        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $codigo = $dados['id_cigam_cliente'];

            $matches = $clienteIndex[$codigo] ?? [];
            if ($matches === []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'codigo' => $dados['codigo'],
                    'erros' => ['Cliente não encontrado na unidade de faturamento desta carteira.'],
                ];

                continue;
            }

            $clientesUnicos = collect($matches)->unique('id')->values();
            if ($clientesUnicos->count() > 1) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'codigo' => $dados['codigo'],
                    'erros' => ['Mais de um cliente corresponde a este ID CIGAM.'],
                ];

                continue;
            }

            /** @var Cliente $cliente */
            $cliente = $clientesUnicos->first();
            $clienteNome = $cliente->fantasia ?: $cliente->razao_social;

            if ((int) $cliente->id_unidade_negocio !== $faturamentoId) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'codigo' => $dados['codigo'],
                    'erros' => ['Cliente não pertence à unidade de faturamento desta carteira.'],
                ];

                continue;
            }

            $carteiraAtual = $cliente->id_captacao_carteira !== null
                ? (int) $cliente->id_captacao_carteira
                : null;

            if ($carteiraAtual !== null && $carteiraAtual === $carteiraId) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'id_cliente' => $cliente->id,
                    'cliente_nome' => $clienteNome,
                    'codigo' => $dados['codigo'],
                ];

                continue;
            }

            if ($carteiraAtual !== null && $carteiraAtual !== $carteiraId) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'codigo' => $dados['codigo'],
                    'erros' => ["A loja «{$clienteNome}» já pertence a outra carteira."],
                ];

                continue;
            }

            $novas[] = [
                'row_id' => $rowId,
                'linha' => $linha,
                'id_cliente' => $cliente->id,
                'cliente_nome' => $clienteNome,
                'codigo' => $dados['codigo'],
                'dados' => $dados,
            ];
        }
    }

    private function mensagemAmigavel(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Allowed memory size')) {
            return 'A planilha excedeu o limite de memória. Remova linhas vazias/formatação extra e tente novamente.';
        }

        if (str_contains($msg, 'Maximum execution time')) {
            return 'O processamento excedeu o tempo limite. Verifique o worker de fila e o tamanho da planilha.';
        }

        return 'Falha ao processar a planilha: '.$msg;
    }

    /**
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function atualizarProgressoSeNecessario(
        CaptacaoCarteiraImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
        array $semAlteracoes,
        array $erros,
    ): void {
        if ($linhasProcessadas % self::PROGRESSO_A_CADA_N_LINHAS !== 0) {
            return;
        }

        $this->salvarProgresso($importacao, $linhasProcessadas, $totalLinhas, $novas, $semAlteracoes, $erros);
    }

    /**
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function salvarProgresso(
        CaptacaoCarteiraImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
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
            'atualizacoes_count' => 0,
            'sem_alteracoes_count' => count($semAlteracoes),
            'erros_count' => count($erros),
        ])->save();
    }

    /**
     * @param  list<mixed>  $row
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
}
