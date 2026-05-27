<?php

namespace App\Services\Movimentacoes;

use App\Enums\FrutaUnidadeMedicao;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaImportacao;
use App\Services\Frutas\FrutaPlanilhaNormalizer;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\Movimentacoes\VendaImportacaoQuantidade;
use App\Support\TextoCadastro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Preview de importação de vendas (NF).
 *
 * Colunas: A NF · B CNPJ origem · C CPF/CNPJ cliente · D id_cigam · E qtd · F UM · G valor total
 */
class VendaImportacaoProcessor
{
    public const MAX_LINHAS_ESCANEADAS = 5000;

    public const MAX_LINHAS_UTEIS = 5000;

    private const PROGRESSO_A_CADA_N_LINHAS = 25;

    public function __construct(
        private readonly UnidadeNegocioAccessService $access,
    ) {}

    public function processar(VendaImportacao $importacao): void
    {
        $importacao->forceFill([
            'status' => VendaImportacao::STATUS_PROCESSANDO,
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
        $reader->setReadFilter(new VendaImportacaoReadFilter(self::MAX_LINHAS_ESCANEADAS));

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

        $linhasVistas = [];

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
                $chaveLinha = $this->chaveUnicidadePlanilha($dados);
                if (isset($linhasVistas[$chaveLinha])) {
                    $errosLinha[] = 'Linha duplicada na planilha (mesma NF, origem, cliente, fruta, quantidade, unidade de medição e valor da linha '.$linhasVistas[$chaveLinha].').';
                } else {
                    $linhasVistas[$chaveLinha] = $r;
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

            $empresaOrigem = $this->resolverEmpresaOrigemPorCnpj($dados['cnpj_origem']);
            if ($empresaOrigem === null) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    'Unidade de origem não encontrada para o CNPJ informado (ou não controla estoque).',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $resolucaoCliente = $this->resolverEmpresaClientePorDocumento($dados['cnpj_cpf_cliente']);
            if ($resolucaoCliente['empresa'] === null) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    $resolucaoCliente['erro'] ?? 'Cliente não encontrado para o CPF/CNPJ informado.',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $empresaCliente = $resolucaoCliente['empresa'];

            $unidadeOrigem = $empresaOrigem->entidade;
            if (! $unidadeOrigem instanceof UnidadeNegocio) {
                $erros[] = $this->erroItem($rowId, $r, $dados, ['Origem não é uma unidade de negócio válida.']);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            if ($unidadeOrigem->is_hub) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    'Origem é HUB; use o cadastro manual de venda informando a unidade de faturamento.',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            if ($user !== null && ! $this->access->canVenda($user, $unidadeOrigem->id)) {
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

            $quantidadeNormalizada = VendaImportacaoQuantidade::normalizar(
                $fruta,
                $dados['qtd_fruta_um'],
                $dados['unidade_medicao'],
            );

            if ($quantidadeNormalizada === null) {
                $erros[] = $this->erroItem($rowId, $r, $dados, [
                    VendaImportacaoQuantidade::mensagemErroUnidadeMedicao(
                        $dados['unidade_medicao'],
                        (string) $fruta->unidade_medicao,
                    ),
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
                    'A unidade de origem nunca recebeu este produto; não é possível registrar a venda.',
                ]);
                $this->atualizarProgressoSeNecessario($importacao, $linhasProcessadas, $totalLinhas, $novas, $erros);

                continue;
            }

            $cliente = $empresaCliente->entidade;
            $nomeOrigem = $unidadeOrigem->nome ?: $unidadeOrigem->razao_social;
            $nomeCliente = $cliente instanceof Cliente
                ? ($cliente->fantasia ?: $cliente->razao_social)
                : '—';

            $novas[] = [
                'row_id' => $rowId,
                'linha' => $r,
                'chave' => $this->chaveExibicao($dados),
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaCliente->id,
                'id_fruta' => $fruta->id,
                'dados' => [
                    'numero_nf' => $dados['numero_nf'],
                    'id_empresa_origem' => $empresaOrigem->id,
                    'id_empresa_destino' => $empresaCliente->id,
                    'id_fruta' => $fruta->id,
                    'qtd_fruta_um' => $quantidadeNormalizada['qtd_fruta_um'],
                    'qtd_planilha' => $quantidadeNormalizada['qtd_planilha'],
                    'unidade_medicao' => $quantidadeNormalizada['unidade_medicao_fruta'],
                    'unidade_medicao_planilha' => $quantidadeNormalizada['unidade_medicao_planilha'],
                    'valor_nf_total' => $dados['valor_nf_total'],
                    'cnpj_origem' => $dados['cnpj_origem'],
                    'cnpj_cpf_cliente' => $dados['cnpj_cpf_cliente'],
                    'nome_origem' => $nomeOrigem,
                    'nome_cliente' => $nomeCliente,
                    'id_cigam_fruta' => $dados['id_cigam_fruta'],
                    'fruta_nome' => $fruta->nome,
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
            'status' => VendaImportacao::STATUS_CONCLUIDO,
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

    public function marcarFalha(VendaImportacao $importacao, \Throwable $e): void
    {
        Log::warning('Importação de vendas falhou', [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $importacao->forceFill([
            'status' => VendaImportacao::STATUS_FALHOU,
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

        $nf = trim((string) ($dadosBrutos[0] ?? ''));
        $cnpjOrigem = TextoCadastro::somenteDigitos((string) ($dadosBrutos[1] ?? ''));
        $docCliente = TextoCadastro::somenteDigitos((string) ($dadosBrutos[2] ?? ''));
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos(trim((string) ($dadosBrutos[3] ?? '')));
        $qtdRaw = trim((string) ($dadosBrutos[4] ?? ''));
        $umRaw = trim((string) ($dadosBrutos[5] ?? ''));
        $valorRaw = trim((string) ($dadosBrutos[6] ?? ''));

        if ($nf === '') {
            $erros[] = 'Número da NF é obrigatório (coluna A).';
        } elseif (strlen($nf) > 255) {
            $erros[] = 'Número da NF pode ter no máximo 255 caracteres.';
        }

        if ($cnpjOrigem === '') {
            $erros[] = 'CNPJ da origem é obrigatório (coluna B).';
        } elseif (! in_array(strlen($cnpjOrigem), [11, 14], true)) {
            $erros[] = 'CNPJ da origem deve ter 11 (CPF) ou 14 (CNPJ) dígitos.';
        }

        if ($docCliente === '') {
            $erros[] = 'CPF/CNPJ do cliente é obrigatório (coluna C).';
        } elseif (! in_array(strlen($docCliente), [11, 14], true)) {
            $erros[] = 'CPF/CNPJ do cliente deve ter 11 ou 14 dígitos.';
        }

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM da fruta é obrigatório (coluna D).';
        }

        $qtdUm = '';
        if ($qtdRaw === '') {
            $erros[] = 'Quantidade é obrigatória (coluna E).';
        } else {
            $qtdUm = TextoCadastro::normalizarDecimalNaoNegativo($qtdRaw);
            if ((float) $qtdUm <= 0) {
                $erros[] = 'A quantidade deve ser maior que zero.';
            }
        }

        $unidadeMedicao = '';
        if ($umRaw === '') {
            $erros[] = 'Unidade de medição é obrigatória (coluna F).';
        } else {
            $unidadeMedicao = FrutaPlanilhaNormalizer::normalizarUnidadeMedicao($umRaw);
            if (! in_array($unidadeMedicao, FrutaUnidadeMedicao::values(), true)) {
                $erros[] = 'Unidade de medição inválida. Valores permitidos: '.implode(', ', FrutaUnidadeMedicao::values()).'.';
            }
        }

        $valorTotal = '';
        if ($valorRaw === '') {
            $erros[] = 'Valor total da linha é obrigatório (coluna G).';
        } else {
            $valorTotal = TextoCadastro::normalizarValorMonetarioBrasileiro($valorRaw);
            if ((float) $valorTotal < 0) {
                $erros[] = 'O valor total não pode ser negativo.';
            }
        }

        return [
            'dados' => [
                'numero_nf' => mb_strtoupper($nf, 'UTF-8'),
                'cnpj_origem' => $cnpjOrigem,
                'cnpj_cpf_cliente' => $docCliente,
                'id_cigam_fruta' => $idCigam,
                'qtd_fruta_um' => $qtdUm,
                'unidade_medicao' => $unidadeMedicao,
                'valor_nf_total' => $valorTotal,
                'linha_planilha' => (string) $linhaPlanilha,
            ],
            'erros' => $erros,
        ];
    }

    private function resolverEmpresaOrigemPorCnpj(string $cnpjDigits): ?Empresa
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
            ->with('entidade')
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidade->id)
            ->first();
    }

    /**
     * @return array{empresa: ?Empresa, erro: ?string}
     */
    private function resolverEmpresaClientePorDocumento(string $documento): array
    {
        $clientes = Cliente::query()->where('cnpj_cpf', $documento)->get();

        if ($clientes->isEmpty()) {
            return [
                'empresa' => null,
                'erro' => 'Cliente não encontrado para o CPF/CNPJ informado.',
            ];
        }

        if ($clientes->count() > 1) {
            return [
                'empresa' => null,
                'erro' => 'Existem vários clientes com o mesmo CPF/CNPJ; use o cadastro manual de venda ou ajuste os cadastros de cliente.',
            ];
        }

        $cliente = $clientes->first();

        $empresa = Empresa::query()
            ->with('entidade')
            ->where('entidade_type', Cliente::class)
            ->where('entidade_id', $cliente->id)
            ->first();

        if ($empresa === null) {
            return [
                'empresa' => null,
                'erro' => 'Cliente não encontrado para o CPF/CNPJ informado.',
            ];
        }

        return ['empresa' => $empresa, 'erro' => null];
    }

    /**
     * @param  array<string, string>  $dados
     */
    private function chaveUnicidadePlanilha(array $dados): string
    {
        return implode('|', [
            $dados['numero_nf'] ?? '',
            $dados['cnpj_origem'] ?? '',
            $dados['cnpj_cpf_cliente'] ?? '',
            $dados['id_cigam_fruta'] ?? '',
            $dados['qtd_fruta_um'] ?? '',
            $dados['unidade_medicao'] ?? '',
            $dados['valor_nf_total'] ?? '',
        ]);
    }

    /**
     * @param  array<string, string>  $dados
     */
    private function chaveExibicao(array $dados): string
    {
        $nf = $dados['numero_nf'] ?? '';
        $orig = $dados['cnpj_origem'] ?? '';
        $cli = $dados['cnpj_cpf_cliente'] ?? '';
        $fruta = $dados['id_cigam_fruta'] ?? '';

        return "NF {$nf} · {$orig} → {$cli} · {$fruta}";
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
        VendaImportacao $importacao,
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
