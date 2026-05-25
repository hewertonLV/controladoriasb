<?php

namespace App\Services\Captacao;

use App\Models\Captacao\ClienteFrutaImportacao;
use App\Models\Cliente;
use App\Models\Fruta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ClienteFrutaVinculoImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    public function __construct(
        private readonly ClienteFrutaVinculoPlanilhaNormalizer $normalizer,
        private readonly ClienteFrutaVinculoService $vinculos,
    ) {}

    public function processar(ClienteFrutaImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => ClienteFrutaImportacao::STATUS_PROCESSANDO,
            'started_at' => $importacao->started_at ?? now(),
            'erro_mensagem' => null,
        ])->save();

        $absoluto = Storage::disk('local')->path($importacao->arquivo_path);

        if (! is_file($absoluto)) {
            throw new \RuntimeException("Arquivo de importação não encontrado: {$importacao->arquivo_path}");
        }

        $faturamentoId = (int) $importacao->id_unidade_negocio_faturamento;
        $clienteIndex = $this->buildClienteIndex($faturamentoId);
        $frutaIndex = $this->buildFrutaIndex();

        $reader = IOFactory::createReaderForFile($absoluto);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter(new ClienteFrutaVinculoImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $reader->load($absoluto);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_LINHAS_ESCANEADAS);
        $totalLinhas = max(0, $highestRow - 1);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $novas = [];
        $semAlteracoes = [];
        $erros = [];

        $paresVistos = [];
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
                    'loja' => '',
                    'fruta' => '',
                    'erros' => ['Limite de '.self::MAX_LINHAS_UTEIS.' linhas úteis excedido. As linhas seguintes foram ignoradas.'],
                ];
                break;
            }

            $rowId++;
            $normalized = $this->normalizer->normalize($dadosBrutos);
            $dados = $normalized['dados'];
            $errosLinha = $normalized['erros'];

            $lojaChave = $dados['loja_chave'];
            $frutaChave = $dados['fruta_chave'];

            if ($lojaChave !== '' && $frutaChave !== '') {
                $chavePar = $lojaChave.'|'.$frutaChave;
                if (isset($paresVistos[$chavePar])) {
                    $errosLinha[] = "Par loja/fruta duplicado na planilha (já aparece na linha {$paresVistos[$chavePar]}).";
                } else {
                    $paresVistos[$chavePar] = $r;
                }
            }

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'loja' => $dados['loja'],
                    'fruta' => $dados['fruta'],
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
                $this->flushBuffer($buffer, $clienteIndex, $frutaIndex, $novas, $semAlteracoes, $erros);
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
            $this->flushBuffer($buffer, $clienteIndex, $frutaIndex, $novas, $semAlteracoes, $erros);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader, $sheet);
        gc_collect_cycles();

        $importacao->forceFill([
            'status' => ClienteFrutaImportacao::STATUS_CONCLUIDO,
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

    public function marcarFalha(ClienteFrutaImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de vínculos fruta×loja falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => ClienteFrutaImportacao::STATUS_FALHOU,
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
            ->get(['id', 'razao_social', 'fantasia']);

        foreach ($clientes as $cliente) {
            foreach ([$cliente->razao_social, $cliente->fantasia] as $nome) {
                $chave = ClienteFrutaVinculoPlanilhaNormalizer::chaveNome($nome);
                if ($chave === '') {
                    continue;
                }

                $index[$chave][] = $cliente;
            }
        }

        return $index;
    }

    /**
     * @return array<string, Fruta>
     */
    private function buildFrutaIndex(): array
    {
        $index = [];

        foreach (Fruta::query()->get(['id', 'nome']) as $fruta) {
            $chave = ClienteFrutaVinculoPlanilhaNormalizer::chaveNome($fruta->nome);
            if ($chave === '') {
                continue;
            }

            if (! isset($index[$chave])) {
                $index[$chave] = $fruta;
            }
        }

        return $index;
    }

    /**
     * @param  array<string, list<Cliente>>  $clienteIndex
     * @param  array<string, Fruta>  $frutaIndex
     * @param  list<array{row_id:int, linha:int, dados:array<string,string>}>  $buffer
     * @param  array<int,mixed>  $novas
     * @param  array<int,mixed>  $semAlteracoes
     * @param  array<int,mixed>  $erros
     */
    private function flushBuffer(
        array $buffer,
        array $clienteIndex,
        array $frutaIndex,
        array &$novas,
        array &$semAlteracoes,
        array &$erros,
    ): void {
        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $lojaChave = $dados['loja_chave'];
            $frutaChave = $dados['fruta_chave'];

            $matches = $clienteIndex[$lojaChave] ?? [];
            if ($matches === []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'loja' => $dados['loja'],
                    'fruta' => $dados['fruta'],
                    'erros' => ['Loja não encontrada nesta unidade de faturamento.'],
                ];

                continue;
            }

            $clientesUnicos = collect($matches)->unique('id')->values();
            if ($clientesUnicos->count() > 1) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'loja' => $dados['loja'],
                    'fruta' => $dados['fruta'],
                    'erros' => ['Mais de uma loja corresponde a este nome. Use razão social ou fantasia únicos.'],
                ];

                continue;
            }

            /** @var Cliente $cliente */
            $cliente = $clientesUnicos->first();

            $fruta = $frutaIndex[$frutaChave] ?? null;
            if ($fruta === null) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'loja' => $dados['loja'],
                    'fruta' => $dados['fruta'],
                    'erros' => ['Fruta não encontrada no cadastro.'],
                ];

                continue;
            }

            $clienteNome = $cliente->fantasia ?: $cliente->razao_social;

            if ($this->vinculos->clientePossuiFruta($cliente->id, $fruta->id)) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'id_cliente' => $cliente->id,
                    'id_fruta' => $fruta->id,
                    'cliente_nome' => $clienteNome,
                    'fruta_nome' => $fruta->nome,
                    'loja' => $dados['loja'],
                    'fruta' => $dados['fruta'],
                ];

                continue;
            }

            $novas[] = [
                'row_id' => $rowId,
                'linha' => $linha,
                'id_cliente' => $cliente->id,
                'id_fruta' => $fruta->id,
                'cliente_nome' => $clienteNome,
                'fruta_nome' => $fruta->nome,
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
        ClienteFrutaImportacao $importacao,
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
        ClienteFrutaImportacao $importacao,
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
