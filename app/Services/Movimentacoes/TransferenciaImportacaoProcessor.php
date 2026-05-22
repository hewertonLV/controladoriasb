<?php

namespace App\Services\Movimentacoes;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\TransferenciaImportacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Preview de importação de transferências.
 *
 * Colunas: A CNPJ origem · B CNPJ destino · C id_cigam fruta · D qtd (UM) · E número NF
 */
class TransferenciaImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    public function __construct(
        private readonly UnidadeNegocioAccessService $access,
    ) {}

    public function processar(TransferenciaImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => TransferenciaImportacao::STATUS_PROCESSANDO,
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
        $reader->setReadFilter(new TransferenciaImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

        /** @var Spreadsheet $spreadsheet */
        $spreadsheet = $reader->load($absoluto);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = min((int) $sheet->getHighestDataRow(), self::MAX_LINHAS_ESCANEADAS);
        $totalLinhas = max(0, $highestRow - 1);

        $importacao->forceFill(['total_linhas' => $totalLinhas])->save();

        $user = $importacao->user_id
            ? User::query()->find($importacao->user_id)
            : null;

        $novas = [];
        $erros = [];

        $rowId = 0;
        $linhasProcessadas = 0;
        $linhasUteis = 0;

        $cnpjVistosPar = [];

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
                    $erros,
                );

                continue;
            }

            if ($linhasUteis >= self::MAX_LINHAS_UTEIS) {
                $erros[] = [
                    'row_id' => 0,
                    'linha' => $r,
                    'chave' => "Linha {$r}",
                    'erros' => ['Limite de '.self::MAX_LINHAS_UTEIS.' linhas úteis atingido.'],
                ];

                continue;
            }

            $linhasUteis++;
            $rowId++;

            $normalizado = $this->normalizarLinha($dadosBrutos, $r);
            $errosLinha = $normalizado['erros'];
            $dados = $normalizado['dados'];

            if ($errosLinha === []) {
                $parChave = $dados['cnpj_origem'].'|'.$dados['cnpj_destino'].'|'.$dados['id_cigam_fruta'];
                if (isset($cnpjVistosPar[$parChave])) {
                    $errosLinha[] = 'Combinação origem/destino/fruta duplicada na planilha (já aparece na linha '.$cnpjVistosPar[$parChave].').';
                } else {
                    $cnpjVistosPar[$parChave] = $r;
                }
            }

            if ($errosLinha !== []) {
                $erros[] = [
                    'row_id' => $rowId,
                    'linha' => $r,
                    'chave' => $this->chaveExibicao($dados),
                    'erros' => $errosLinha,
                ];
                $this->atualizarProgressoSeNecessario(
                    $importacao,
                    $linhasProcessadas,
                    $totalLinhas,
                    $novas,
                    $erros,
                );

                continue;
            }

            $empresaOrigem = $this->resolverEmpresaPorCnpj($dados['cnpj_origem']);
            if ($empresaOrigem === null) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    'Unidade de origem não encontrada para o CNPJ informado (ou não controla estoque).',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $empresaDestino = $this->resolverEmpresaPorCnpj($dados['cnpj_destino']);
            if ($empresaDestino === null) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    'Unidade de destino não encontrada para o CNPJ informado (ou não controla estoque).',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            if ($empresaOrigem->id === $empresaDestino->id) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    'Origem e destino não podem ser a mesma unidade de negócio.',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $unidadeOrigem = $empresaOrigem->entidade;
            if (! $unidadeOrigem instanceof UnidadeNegocio) {
                $erros[] = $this->erroItem($rowId, $r, $dados, ['Origem não é uma unidade de negócio válida.']);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            if ($user !== null && ! $this->access->canTransferencia($user, $unidadeOrigem->id)) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO,
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $fruta = Fruta::query()->where('id_cigam', $dados['id_cigam_fruta'])->first();
            if ($fruta === null) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    "Fruta com id_cigam {$dados['id_cigam_fruta']} não encontrada.",
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            if ((float) $fruta->kg_por_unidade_medicao <= 0) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    'A fruta precisa ter kg por unidade de medição maior que zero.',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $temEstoqueOrigem = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->exists();

            if (! $temEstoqueOrigem) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    'A unidade de origem nunca recebeu este produto; por isso não é possível executar esta transferência.',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $unidadeDestino = $empresaDestino->entidade;
            $nomeOrigem = $unidadeOrigem instanceof UnidadeNegocio ? ($unidadeOrigem->nome ?: $unidadeOrigem->razao_social) : '—';
            $nomeDestino = $unidadeDestino instanceof UnidadeNegocio ? ($unidadeDestino->nome ?: $unidadeDestino->razao_social) : '—';

            $novas[] = [
                'row_id' => $rowId,
                'linha' => $r,
                'chave' => $this->chaveExibicao($dados),
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_fruta' => $fruta->id,
                'dados' => [
                    'id_empresa_origem' => $empresaOrigem->id,
                    'id_empresa_destino' => $empresaDestino->id,
                    'id_fruta' => $fruta->id,
                    'qtd_fruta_um' => $dados['qtd_fruta_um'],
                    'numero_nf_origem' => $dados['numero_nf_origem'],
                    'cnpj_origem' => $dados['cnpj_origem'],
                    'cnpj_destino' => $dados['cnpj_destino'],
                    'nome_origem' => $nomeOrigem,
                    'nome_destino' => $nomeDestino,
                    'id_cigam_fruta' => $dados['id_cigam_fruta'],
                    'fruta_nome' => $fruta->nome,
                    'unidade_medicao' => $fruta->unidade_medicao,
                ],
            ];

            $this->atualizarProgressoSeNecessario(
                $importacao,
                $linhasProcessadas,
                $totalLinhas,
                $novas,
                $erros,
            );
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $reader, $sheet);
        gc_collect_cycles();

        $resultado = [
            'novas' => $novas,
            'atualizacoes' => [],
            'sem_alteracoes' => [],
            'erros' => $erros,
        ];

        $importacao->forceFill([
            'status' => TransferenciaImportacao::STATUS_CONCLUIDO,
            'finished_at' => now(),
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => 100,
            'novas_count' => count($novas),
            'atualizacoes_count' => 0,
            'sem_alteracoes_count' => 0,
            'erros_count' => count($erros),
            'resultado' => $resultado,
        ])->save();
    }

    public function marcarFalha(TransferenciaImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de transferências falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => TransferenciaImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => $this->mensagemAmigavel($e),
        ])->save();
    }

    /**
     * @param  list<mixed>  $dadosBrutos
     * @return array{dados: array<string, string>, erros: list<string>}
     */
    private function normalizarLinha(array $dadosBrutos, int $linhaPlanilha): array
    {
        $erros = [];

        $cnpjOrigem = TextoCadastro::somenteDigitos((string) ($dadosBrutos[0] ?? ''));
        $cnpjDestino = TextoCadastro::somenteDigitos((string) ($dadosBrutos[1] ?? ''));
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos(trim((string) ($dadosBrutos[2] ?? '')));
        $qtdRaw = trim((string) ($dadosBrutos[3] ?? ''));
        $nf = trim((string) ($dadosBrutos[4] ?? ''));

        if ($cnpjOrigem === '') {
            $erros[] = 'CNPJ da origem é obrigatório (coluna A).';
        } elseif (! in_array(strlen($cnpjOrigem), [11, 14], true)) {
            $erros[] = 'CNPJ da origem deve ter 11 (CPF) ou 14 (CNPJ) dígitos.';
        }

        if ($cnpjDestino === '') {
            $erros[] = 'CNPJ do destino é obrigatório (coluna B).';
        } elseif (! in_array(strlen($cnpjDestino), [11, 14], true)) {
            $erros[] = 'CNPJ do destino deve ter 11 (CPF) ou 14 (CNPJ) dígitos.';
        }

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM da fruta é obrigatório (coluna C).';
        }

        $qtdUm = '';
        if ($qtdRaw === '') {
            $erros[] = 'Quantidade na unidade de medição é obrigatória (coluna D).';
        } else {
            $qtdUm = TextoCadastro::normalizarDecimalNaoNegativo($qtdRaw);
            if ((float) $qtdUm <= 0) {
                $erros[] = 'A quantidade deve ser maior que zero.';
            }
        }

        if ($nf === '') {
            $erros[] = 'Número da NF é obrigatório (coluna E).';
        } elseif (strlen($nf) > 120) {
            $erros[] = 'Número da NF pode ter no máximo 120 caracteres.';
        }

        return [
            'dados' => [
                'cnpj_origem' => $cnpjOrigem,
                'cnpj_destino' => $cnpjDestino,
                'id_cigam_fruta' => $idCigam,
                'qtd_fruta_um' => $qtdUm,
                'numero_nf_origem' => $nf,
                'linha_planilha' => (string) $linhaPlanilha,
            ],
            'erros' => $erros,
        ];
    }

    private function resolverEmpresaPorCnpj(string $cnpjDigits): ?Empresa
    {
        $unidades = UnidadeNegocio::query()
            ->where('cpf_cnpj', $cnpjDigits)
            ->where('possui_estoque', true)
            ->get();

        if ($unidades->count() !== 1) {
            return null;
        }

        $unidade = $unidades->first();

        return Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidade->id)
            ->first();
    }

    /**
     * @param  array<string, string>  $dados
     */
    private function chaveExibicao(array $dados): string
    {
        $orig = $dados['cnpj_origem'] ?? '';
        $dest = $dados['cnpj_destino'] ?? '';
        $fruta = $dados['id_cigam_fruta'] ?? '';
        $nf = $dados['numero_nf_origem'] ?? '';

        return "{$orig} → {$dest} · {$fruta} · NF {$nf}";
    }

    /**
     * @param  array<string, string>  $dados
     * @param  list<string>  $mensagens
     * @return array<string, mixed>
     */
    private function erroItem(int $rowId, int $linha, array $dados, array $mensagens): array
    {
        return [
            'row_id' => $rowId,
            'linha' => $linha,
            'chave' => $this->chaveExibicao($dados),
            'erros' => $mensagens,
        ];
    }

    /**
     * @param  array<int, mixed>  $novas
     * @param  array<int, mixed>  $erros
     */
    private function atualizarProgressoSeNecessario(
        TransferenciaImportacao $importacao,
        int $linhasProcessadas,
        int $totalLinhas,
        array $novas,
        array $erros,
    ): void {
        if ($linhasProcessadas % self::PROGRESSO_A_CADA_N_LINHAS !== 0) {
            return;
        }

        $percentual = $totalLinhas > 0
            ? (int) min(99, floor($linhasProcessadas * 100 / $totalLinhas))
            : 0;

        $importacao->forceFill([
            'linhas_processadas' => $linhasProcessadas,
            'percentual' => $percentual,
            'novas_count' => count($novas),
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

    private function mensagemAmigavel(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'memory') || str_contains($msg, 'Memory')) {
            return 'A planilha é grande demais para processar. Reduza o número de linhas ou divida em arquivos menores.';
        }

        return 'Falha ao processar a planilha: '.$msg;
    }
}
