<?php

namespace App\Services\Estoques;

use App\Models\Estoque;
use App\Models\EstoqueImportacao;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Preview de importação de posição consolidada de estoques.
 *
 * Colunas: A id_cigam unidade · B id_cigam fruta · C qtd_fruta_kg · D preco_medio_kg
 */
class EstoqueImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const CHUNK_DB = 100;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    public function processar(EstoqueImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => EstoqueImportacao::STATUS_PROCESSANDO,
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
        $reader->setReadFilter(new EstoqueImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

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

        $chaveVista = [];
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
                    'chave' => '',
                    'erros' => ['Limite de '.self::MAX_LINHAS_UTEIS.' linhas úteis excedido. As linhas seguintes foram ignoradas.'],
                    'dados' => [],
                ];
                break;
            }

            $rowId++;
            $normalized = $this->normalizeRow($dadosBrutos);
            $dados = $normalized['dados'];
            $errosLinha = $normalized['erros'];
            $chave = $dados['id_cigam_unidade'].'|'.$dados['id_cigam_fruta'];

            if ($chave !== '|' && isset($chaveVista[$chave])) {
                $errosLinha[] = "Combinação unidade+fruta duplicada na planilha (linha {$chaveVista[$chave]}).";
            } elseif ($chave !== '|') {
                $chaveVista[$chave] = $r;
            }

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'chave' => $chave,
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
            'status' => EstoqueImportacao::STATUS_CONCLUIDO,
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

    public function marcarFalha(EstoqueImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de estoques falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => EstoqueImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => $this->mensagemAmigavel($e),
        ])->save();
    }

    /**
     * @param  list<mixed>  $dadosBrutos
     * @return array{dados: array{id_cigam_unidade: string, id_cigam_unidade_original: string, id_cigam_fruta: string, qtd_fruta_kg: string, preco_medio_kg: string}, erros: list<string>}
     */
    private function normalizeRow(array $dadosBrutos): array
    {
        $idUnOriginal = trim((string) ($dadosBrutos[0] ?? ''));
        $idUn = TextoCadastro::normalizarIdCigam($idUnOriginal);
        $idFr = TextoCadastro::normalizarIdCigam((string) ($dadosBrutos[1] ?? ''));
        $qtdRaw = trim((string) ($dadosBrutos[2] ?? '0'));
        $precoRaw = trim((string) ($dadosBrutos[3] ?? '0'));

        $erros = [];

        if ($idUn === '') {
            $erros[] = 'ID CIGAM da unidade (coluna A) é obrigatório.';
        } elseif (! preg_match('/^\d{6}$/', $idUn)) {
            $erros[] = 'ID CIGAM da unidade deve ter até 6 dígitos numéricos.';
        }

        if ($idFr === '') {
            $erros[] = 'ID CIGAM da fruta (coluna B) é obrigatório.';
        } elseif (! preg_match('/^\d{6}$/', $idFr)) {
            $erros[] = 'ID CIGAM da fruta deve ter até 6 dígitos numéricos.';
        }

        $qtd = max(0, round((float) str_replace(',', '.', $qtdRaw), 2));
        $preco = max(0, round((float) str_replace(',', '.', $precoRaw), 2));

        return [
            'dados' => [
                'id_cigam_unidade' => $idUn,
                'id_cigam_unidade_original' => $idUnOriginal,
                'id_cigam_fruta' => $idFr,
                'qtd_fruta_kg' => number_format($qtd, 2, '.', ''),
                'preco_medio_kg' => number_format($preco, 2, '.', ''),
            ],
            'erros' => $erros,
        ];
    }

    private function mensagemAmigavel(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'Allowed memory size')) {
            return 'A planilha excedeu o limite de memória. Remova linhas vazias/formatação extra, salve novamente como .xlsx limpo e tente outra vez.';
        }

        if (str_contains($msg, 'Maximum execution time')) {
            return 'O processamento excedeu o tempo limite. Configure o worker de fila com `--timeout=900`.';
        }

        return 'Falha ao processar a planilha: '.$msg;
    }

    /**
     * @param  list<array{row_id:int, linha:int, dados:array<string,mixed>}>  $buffer
     * @param  array<int, mixed>  $novas
     * @param  array<int, mixed>  $atualizacoes
     * @param  array<int, mixed>  $semAlteracoes
     * @param  array<int, mixed>  $erros
     */
    private function flushBuffer(
        array $buffer,
        array &$novas,
        array &$atualizacoes,
        array &$semAlteracoes,
        array &$erros,
    ): void {
        $idsUn = [];
        $idsFr = [];
        foreach ($buffer as $item) {
            $idsUn[] = $item['dados']['id_cigam_unidade'];
            $idsFr[] = $item['dados']['id_cigam_fruta'];
        }

        $unidades = UnidadeNegocio::query()
            ->whereIn('id_cigam', array_values(array_unique($idsUn)))
            ->get()
            ->keyBy('id_cigam');

        $frutas = Fruta::query()
            ->whereIn('id_cigam', array_values(array_unique($idsFr)))
            ->get()
            ->keyBy('id_cigam');

        $uniIdsInternos = $unidades->pluck('id')->all();
        $fruIdsInternos = $frutas->pluck('id')->all();

        $estoques = Estoque::query()
            ->whereIn('id_unidade_negocio', $uniIdsInternos !== [] ? $uniIdsInternos : [-1])
            ->whereIn('id_fruta', $fruIdsInternos !== [] ? $fruIdsInternos : [-1])
            ->get()
            ->keyBy(fn (Estoque $e) => $e->id_unidade_negocio.'_'.$e->id_fruta);

        foreach ($buffer as $item) {
            $rowId = $item['row_id'];
            $linha = $item['linha'];
            $dados = $item['dados'];
            $idUnCigam = $dados['id_cigam_unidade'];
            $idFrCigam = $dados['id_cigam_fruta'];

            $unidade = $unidades->get($idUnCigam);
            if ($unidade === null) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'chave' => $idUnCigam.'|'.$idFrCigam,
                    'erros' => [$this->mensagemUnidadeNaoEncontrada($idUnCigam, (string) ($dados['id_cigam_unidade_original'] ?? ''))],
                    'dados' => $dados,
                ];

                continue;
            }

            if (! $unidade->possui_estoque) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'chave' => $idUnCigam.'|'.$idFrCigam,
                    'erros' => ['A unidade informada não possui controle de estoque (possui_estoque = não).'],
                    'dados' => $dados,
                ];

                continue;
            }

            $fruta = $frutas->get($idFrCigam);
            if ($fruta === null) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'chave' => $idUnCigam.'|'.$idFrCigam,
                    'erros' => ['Fruta não encontrada para o ID CIGAM informado.'],
                    'dados' => $dados,
                ];

                continue;
            }

            if ((float) $fruta->kg_por_unidade_medicao <= 0) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'chave' => $idUnCigam.'|'.$idFrCigam,
                    'erros' => ['A fruta precisa ter kg por unidade de medição maior que zero.'],
                    'dados' => $dados,
                ];

                continue;
            }

            $k = $unidade->id.'_'.$fruta->id;
            $existente = $estoques->get($k);

            if ($existente === null) {
                $novas[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'id_unidade_negocio' => $unidade->id,
                    'id_fruta' => $fruta->id,
                    'chave' => $idUnCigam.'|'.$idFrCigam,
                    'dados' => $dados,
                ];

                continue;
            }

            $qAtual = number_format((float) $existente->qtd_fruta_kg, 2, '.', '');
            $pAtual = number_format((float) $existente->preco_medio_kg, 2, '.', '');
            $qNovo = $dados['qtd_fruta_kg'];
            $pNovo = $dados['preco_medio_kg'];

            if ($qAtual === $qNovo && $pAtual === $pNovo) {
                $semAlteracoes[] = [
                    'row_id' => $rowId,
                    'linha' => $linha,
                    'estoque_id' => $existente->id,
                    'chave' => $idUnCigam.'|'.$idFrCigam,
                    'nome' => $idUnCigam.'|'.$idFrCigam,
                ];

                continue;
            }

            $camposAlterados = [];
            if ($qAtual !== $qNovo) {
                $camposAlterados[] = ['campo' => 'qtd_fruta_kg', 'atual' => $qAtual, 'novo' => $qNovo];
            }
            if ($pAtual !== $pNovo) {
                $camposAlterados[] = ['campo' => 'preco_medio_kg', 'atual' => $pAtual, 'novo' => $pNovo];
            }

            $atualizacoes[] = [
                'row_id' => $rowId,
                'linha' => $linha,
                'estoque_id' => $existente->id,
                'id_unidade_negocio' => $unidade->id,
                'id_fruta' => $fruta->id,
                'chave' => $idUnCigam.'|'.$idFrCigam,
                'nome' => $idUnCigam.'|'.$idFrCigam,
                'dados_atuais' => [
                    'qtd_fruta_kg' => $qAtual,
                    'preco_medio_kg' => $pAtual,
                ],
                'dados_novos' => [
                    'qtd_fruta_kg' => $qNovo,
                    'preco_medio_kg' => $pNovo,
                ],
                'campos_alterados' => $camposAlterados,
            ];
        }
    }

    /**
     * @param  array<int, mixed>  $novas
     * @param  array<int, mixed>  $atualizacoes
     * @param  array<int, mixed>  $semAlteracoes
     * @param  array<int, mixed>  $erros
     */
    private function atualizarProgressoSeNecessario(
        EstoqueImportacao $importacao,
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

    private function mensagemUnidadeNaoEncontrada(string $normalizado, string $original): string
    {
        return "Unidade de negócio com id_cigam {$normalizado} não encontrada. Valor original informado: {$original}.";
    }

    /**
     * @param  array<int, mixed>  $novas
     * @param  array<int, mixed>  $atualizacoes
     * @param  array<int, mixed>  $semAlteracoes
     * @param  array<int, mixed>  $erros
     */
    private function salvarProgresso(
        EstoqueImportacao $importacao,
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
     * @param  array<int, mixed>  $row
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
