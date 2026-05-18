<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasDoacaoOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoEmpresaRegistro;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Support\EmpresaEntidadeQuery;
use App\Support\Movimentacoes\DoacaoValorEconomico;
use App\Support\Movimentacoes\FrutasComEstoqueOrigem;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras e persistência exclusivas da categoria DOAÇÃO (saída simples com custo médio preservado).
 */
final class DoacaoMovimentacaoService
{
    public function __construct(
        private readonly MovimentacaoVersionamentoService $versionamento,
        private readonly ReprocessaSaidasDoacaoOrigem $replayDoacao,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    /**
     * @return array{
     *     empresas_origem: Collection<int, Empresa>,
     *     empresas_destino_cliente: Collection<int, Empresa>,
     *     frutas: Collection<int, Fruta>,
     * }
     */
    public function opcoesFormularioDoacao(): array
    {
        $empresasOrigem = EmpresaEntidadeQuery::unidadesComEstoque(somenteComEstoqueCadastrado: true)
            ->with('entidade')
            ->get()
            ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
            ->values();

        $empresasDestinoCliente = Empresa::query()
            ->where('entidade_type', Cliente::class)
            ->with('entidade')
            ->get()
            ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
            ->values();

        return [
            'empresas_origem' => $empresasOrigem,
            'empresas_destino_cliente' => $empresasDestinoCliente,
            'frutas' => FrutasComEstoqueOrigem::listar(),
        ];
    }

    /**
     * @param  array{
     *     id_empresa_origem:int,
     *     id_empresa_destino?:int|null,
     *     id_fruta:int,
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     motivo_doacao:string,
     *     observacao?:string|null,
     *     numero_nf_origem?:string|null,
     * }  $input
     */
    public function registrarDoacao(array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($input, $user): Movimentacao {
            $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $input['id_empresa_origem']);
            $this->assertEmpresaTipo($empresaOrigem, TipoEmpresaRegistro::UNIDADE_NEGOCIO);
            $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
            if (! $unidadeOrigem->possui_estoque) {
                throw new InvalidArgumentException('A unidade de origem deve controlar estoque.');
            }

            $idEmpresaDestino = isset($input['id_empresa_destino']) && $input['id_empresa_destino'] !== null && $input['id_empresa_destino'] !== ''
                ? (int) $input['id_empresa_destino']
                : null;

            if ($idEmpresaDestino !== null) {
                $empresaDestino = Empresa::query()->with('entidade')->findOrFail($idEmpresaDestino);
                $this->assertEmpresaTipo($empresaDestino, TipoEmpresaRegistro::CLIENTE);
            }

            $fruta = Fruta::query()->findOrFail((int) $input['id_fruta']);
            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
            if ($kgPorUm <= 0) {
                throw new InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
            }

            $qtdUm = round((float) TextoCadastro::normalizarDecimalNaoNegativo($input['qtd_fruta_um']), 2);
            if ($qtdUm <= 0) {
                throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
            }

            $qtdKg = round($qtdUm * $kgPorUm, 2);
            if ($qtdKg <= 0) {
                throw new InvalidArgumentException('Quantidade em KG calculada inválida.');
            }

            $motivoDoacao = trim((string) $input['motivo_doacao']);
            if ($motivoDoacao === '') {
                throw new InvalidArgumentException('O motivo da doação é obrigatório.');
            }

            $observacao = isset($input['observacao']) ? trim((string) $input['observacao']) : null;
            if ($observacao === '') {
                $observacao = null;
            }

            $numeroNfOrigem = isset($input['numero_nf_origem']) ? trim((string) $input['numero_nf_origem']) : null;
            if ($numeroNfOrigem === '') {
                $numeroNfOrigem = null;
            }

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->garantirPosicaoInicialSeNecessario($estoque, $unidadeOrigem->id, $fruta->id);

            $posicaoOrigem = MovimentacaoEstoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->where('status_ultima_posicao', true)
                ->lockForUpdate()
                ->first();

            $saldoUmAnt = $posicaoOrigem ? (float) $posicaoOrigem->qtd_fruta_um : 0.0;
            $saldoKgAnt = $posicaoOrigem ? (float) $posicaoOrigem->qtd_fruta_kg : 0.0;

            if ($saldoUmAnt + 1e-6 < $qtdUm || $saldoKgAnt + 1e-6 < $qtdKg) {
                throw new InvalidArgumentException('Saldo insuficiente na unidade de origem.');
            }

            $Vprev = (float) $estoque->valor_total_acumulado;
            $precoMedioKg = (float) $estoque->preco_medio_kg;
            $precoMedioUm = (float) $estoque->preco_medio_um;

            $valorEconomicoTotal = round($precoMedioKg * $qtdKg, 2);

            $valorMeAnt = $posicaoOrigem !== null
                ? (float) $posicaoOrigem->valor_total_fruta
                : (float) $estoque->valor_total_acumulado;
            $valorMeNovo = round($valorMeAnt - $valorEconomicoTotal, 2);

            $saldoUmNovo = round($saldoUmAnt - $qtdUm, 2);
            $saldoKgNovo = round($saldoKgAnt - $qtdKg, 2);
            $Vnovo = round($Vprev - $valorEconomicoTotal, 2);

            $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
            $meAntes = $posicaoOrigem !== null ? $this->auditoria->snapshotMovimentacaoEstoque($posicaoOrigem) : null;

            if ($posicaoOrigem !== null) {
                $posicaoOrigem->forceFill(['status_ultima_posicao' => false])->save();
            }

            /** @var Movimentacao $movimentacao */
            $movimentacao = Movimentacao::query()->create([
                'id_movimentacao_estoque_old' => $posicaoOrigem?->id,
                'id_movimentacao_estoque_new' => null,
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $idEmpresaDestino,
                'id_fruta' => $fruta->id,
                'valor_nf_total' => number_format(0, 2, '.', ''),
                'valor_nf_um' => number_format(0, 2, '.', ''),
                'valor_nf_kg' => number_format(0, 2, '.', ''),
                'valor_total_movimentacao' => number_format($valorEconomicoTotal, 2, '.', ''),
                'valor_icms_total' => number_format(0, 2, '.', ''),
                'valor_icms_kg' => number_format(0, 2, '.', ''),
                'valor_icms_um' => number_format(0, 2, '.', ''),
                'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
                'id_frete' => null,
                'valor_frete_rateio' => number_format(0, 2, '.', ''),
                'valor_frete_um' => number_format(0, 2, '.', ''),
                'valor_frete_kg' => number_format(0, 2, '.', ''),
                'id_custo_operacional' => null,
                'valor_custo_operacional' => number_format(0, 2, '.', ''),
                'saldo_estoque_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_fruta_kg' => number_format($precoMedioKg, 2, '.', ''),
                'preco_medio_fruta_um' => number_format($precoMedioUm, 2, '.', ''),
                'icms_convertido_kg' => number_format(0, 2, '.', ''),
                'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Doacao->value,
                'status_movimentacao_id' => StatusMovimentacao::ID_SAIDA,
                'status_transferencia' => null,
                'transferencia_origem_id' => null,
                'pareada_movimentacao_id' => null,
                'numero_nf_origem' => $numeroNfOrigem,
                'numero_nf_destino' => null,
                'qtd_recebida_um' => null,
                'qtd_recebida_kg' => null,
                'status_recebimento' => null,
                'observacao' => $observacao,
                'observacao_recebimento' => null,
                'motivo_doacao' => $motivoDoacao,
                'movimentacao_origem_id' => null,
                'versao' => 1,
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
                'data_movimentacao' => now(),
            ]);

            $novaPosicaoOrigem = MovimentacaoEstoque::query()->create([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $unidadeOrigem->id,
                'id_fruta' => $fruta->id,
                'movimentacao_id' => $movimentacao->id,
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
                'valor_total_fruta' => number_format($valorMeNovo, 2, '.', ''),
                'status_ultima_posicao' => true,
            ]);

            $movimentacao->forceFill(['id_movimentacao_estoque_new' => $novaPosicaoOrigem->id])->saveQuietly();

            $estoque->forceFill([
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
                'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
            ])->save();

            $estoqueDepois = $this->auditoria->snapshotEstoque($estoque->fresh());
            $meDepois = $this->auditoria->snapshotMovimentacaoEstoque($novaPosicaoOrigem->fresh());

            $this->auditoria->registrarRegistroDoacao(
                $movimentacao->fresh(),
                $user,
                $estoqueAntes,
                $estoqueDepois,
                $meAntes,
                $meDepois,
            );

            return $movimentacao->fresh(['fruta', 'empresaOrigem', 'empresaDestino']);
        });
    }

    /**
     * @param  array{
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     id_empresa_destino?:int|null,
     *     motivo_doacao:string,
     *     observacao?:string|null,
     *     numero_nf_origem?:string|null,
     *     motivo_substituicao?:string|null,
     * }  $input
     */
    public function atualizarDoacao(Movimentacao $movimentacao, array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($movimentacao, $input, $user): Movimentacao {
            $motivo = isset($input['motivo_substituicao']) ? trim((string) $input['motivo_substituicao']) : null;
            if ($motivo === '') {
                $motivo = null;
            }
            unset($input['motivo_substituicao']);

            $ativa = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();
            $this->assertDoacaoSaidaAtiva($ativa);
            $this->versionamento->validarPodeSubstituir($ativa);

            $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $ativa->id_empresa_origem);
            $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);

            $idEmpresaDestino = array_key_exists('id_empresa_destino', $input)
                ? ($input['id_empresa_destino'] === null || $input['id_empresa_destino'] === '' ? null : (int) $input['id_empresa_destino'])
                : (int) $ativa->id_empresa_destino;
            if ($idEmpresaDestino === 0) {
                $idEmpresaDestino = null;
            }
            if ($idEmpresaDestino !== null) {
                $empresaDestino = Empresa::query()->with('entidade')->findOrFail($idEmpresaDestino);
                $this->assertEmpresaTipo($empresaDestino, TipoEmpresaRegistro::CLIENTE);
            }

            $fruta = Fruta::query()->findOrFail((int) $ativa->id_fruta);
            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
            if ($kgPorUm <= 0) {
                throw new InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
            }

            $qtdUm = round((float) TextoCadastro::normalizarDecimalNaoNegativo($input['qtd_fruta_um']), 2);
            if ($qtdUm <= 0) {
                throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
            }

            $qtdKg = round($qtdUm * $kgPorUm, 2);

            $motivoDoacao = trim((string) $input['motivo_doacao']);
            if ($motivoDoacao === '') {
                throw new InvalidArgumentException('O motivo da doação é obrigatório.');
            }

            $observacao = isset($input['observacao']) ? trim((string) $input['observacao']) : null;
            if ($observacao === '') {
                $observacao = null;
            }

            $numeroNfOrigem = isset($input['numero_nf_origem']) ? trim((string) $input['numero_nf_origem']) : null;
            if ($numeroNfOrigem === '') {
                $numeroNfOrigem = null;
            }

            $qtdUmAntiga = (float) $ativa->qtd_fruta_um;
            $qKgAntiga = (float) $ativa->qtd_fruta_kg;

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $saldoUmAtual = (float) $estoque->qtd_fruta_um;
            $disponivelUm = round($saldoUmAtual + $qtdUmAntiga, 2);
            if ($qtdUm > $disponivelUm + 1e-6) {
                throw new InvalidArgumentException('Saldo insuficiente na unidade de origem para a nova quantidade.');
            }

            $precoMedioKg = (float) $estoque->preco_medio_kg;
            $precoMedioUm = (float) $estoque->preco_medio_um;
            $valorEconomicoTotal = round($precoMedioKg * $qtdKg, 2);

            $saldoKgNovo = round((float) $estoque->qtd_fruta_kg + $qKgAntiga - $qtdKg, 2);
            $saldoUmNovo = round((float) $estoque->qtd_fruta_um + $qtdUmAntiga - $qtdUm, 2);

            $novaLinha = [
                'id_movimentacao_estoque_old' => $ativa->id_movimentacao_estoque_old,
                'id_movimentacao_estoque_new' => null,
                'id_empresa_origem' => $ativa->id_empresa_origem,
                'id_empresa_destino' => $idEmpresaDestino,
                'id_fruta' => $ativa->id_fruta,
                'valor_nf_total' => number_format(0, 2, '.', ''),
                'valor_nf_um' => number_format(0, 2, '.', ''),
                'valor_nf_kg' => number_format(0, 2, '.', ''),
                'valor_total_movimentacao' => number_format($valorEconomicoTotal, 2, '.', ''),
                'valor_icms_total' => number_format(0, 2, '.', ''),
                'valor_icms_kg' => number_format(0, 2, '.', ''),
                'valor_icms_um' => number_format(0, 2, '.', ''),
                'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
                'id_frete' => null,
                'valor_frete_rateio' => number_format(0, 2, '.', ''),
                'valor_frete_um' => number_format(0, 2, '.', ''),
                'valor_frete_kg' => number_format(0, 2, '.', ''),
                'id_custo_operacional' => null,
                'valor_custo_operacional' => number_format(0, 2, '.', ''),
                'saldo_estoque_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_fruta_kg' => number_format($precoMedioKg, 2, '.', ''),
                'preco_medio_fruta_um' => number_format($precoMedioUm, 2, '.', ''),
                'icms_convertido_kg' => number_format(0, 2, '.', ''),
                'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Doacao->value,
                'status_movimentacao_id' => StatusMovimentacao::ID_SAIDA,
                'status_transferencia' => null,
                'transferencia_origem_id' => null,
                'pareada_movimentacao_id' => null,
                'numero_nf_origem' => $numeroNfOrigem,
                'numero_nf_destino' => null,
                'qtd_recebida_um' => null,
                'qtd_recebida_kg' => null,
                'status_recebimento' => null,
                'observacao' => $observacao,
                'observacao_recebimento' => null,
                'motivo_doacao' => $motivoDoacao,
                'cancelada_por' => null,
                'cancelada_em' => null,
                'motivo_cancelamento' => null,
            ];

            $nova = $this->versionamento->criarNovaVersao($ativa, $novaLinha, $motivo, $user);

            $estoqueAjuste = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $deltaUm = round($qtdUm - $qtdUmAntiga, 2);
            $deltaKg = round($qtdKg - $qKgAntiga, 2);
            $precoKgAjuste = (float) $estoqueAjuste->preco_medio_kg;
            $precoUmAjuste = (float) $estoqueAjuste->preco_medio_um;
            $valorAntigo = DoacaoValorEconomico::valorTotalMovimentacao($ativa);
            $valorNovo = round($precoKgAjuste * $qtdKg, 2);
            $deltaValor = round($valorNovo - $valorAntigo, 2);

            $estoqueAjuste->forceFill([
                'qtd_fruta_um' => number_format(round((float) $estoqueAjuste->qtd_fruta_um - $deltaUm, 2), 2, '.', ''),
                'qtd_fruta_kg' => number_format(round((float) $estoqueAjuste->qtd_fruta_kg - $deltaKg, 2), 2, '.', ''),
                'preco_medio_kg' => number_format($precoKgAjuste, 2, '.', ''),
                'preco_medio_um' => number_format($precoUmAjuste, 2, '.', ''),
                'valor_total_acumulado' => number_format(
                    round((float) $estoqueAjuste->valor_total_acumulado - $deltaValor, 2),
                    2,
                    '.',
                    '',
                ),
            ])->save();

            $this->replayDoacao->reprocessarSaidasDoacaoNaUnidadeOrigem($unidadeOrigem->id, $fruta->id);

            return $nova->fresh(['fruta', 'empresaOrigem', 'empresaDestino']);
        });
    }

    /**
     * Estorna a saída de doação no estoque de origem (devolve quantidade e valor acumulado, preservando preço médio da linha).
     */
    public function estornarDoacaoNoEstoqueOrigem(Movimentacao $doacao): void
    {
        if ((int) $doacao->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Doacao->value) {
            throw new InvalidArgumentException('Somente doações podem ser estornadas por este fluxo.');
        }
        if ((int) $doacao->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
            throw new InvalidArgumentException('Somente saída de doação pode ser estornada no estoque de origem.');
        }

        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $doacao->id_empresa_origem);
        $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
        $frutaId = (int) $doacao->id_fruta;

        $qtdUm = (float) $doacao->qtd_fruta_um;
        $qtdKg = (float) $doacao->qtd_fruta_kg;

        $estoqueOrigem = Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $frutaId)
            ->lockForUpdate()
            ->firstOrFail();

        $posicaoAtual = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $frutaId)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->firstOrFail();

        $precoKg = (float) $doacao->preco_medio_fruta_kg;
        $precoUm = (float) $doacao->preco_medio_fruta_um;

        $saldoUmAnt = (float) $posicaoAtual->qtd_fruta_um;
        $saldoKgAnt = (float) $posicaoAtual->qtd_fruta_kg;
        $Vprev = (float) $estoqueOrigem->valor_total_acumulado;

        $valorDevolvido = DoacaoValorEconomico::valorTotalMovimentacao($doacao);
        $valorMeAnt = (float) $posicaoAtual->valor_total_fruta;

        $saldoUmNovo = round($saldoUmAnt + $qtdUm, 2);
        $saldoKgNovo = round($saldoKgAnt + $qtdKg, 2);
        $Vnovo = round($Vprev + $valorDevolvido, 2);
        $valorMeNovo = round($valorMeAnt + $valorDevolvido, 2);

        $posicaoAtual->forceFill(['status_ultima_posicao' => false])->save();

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueOrigem->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $frutaId,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorMeNovo, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoqueOrigem->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
        ])->save();
    }

    private function assertDoacaoSaidaAtiva(Movimentacao $m): void
    {
        if ((int) $m->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Doacao->value) {
            throw new InvalidArgumentException('Somente movimentações da categoria DOAÇÃO.');
        }
        if ((int) $m->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
            throw new InvalidArgumentException('Somente saídas de doação podem ser alteradas por este fluxo.');
        }
        if ($m->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
            throw new InvalidArgumentException('Somente versões ativas podem ser atualizadas.');
        }
    }

    private function unidadeDaEmpresa(Empresa $empresa): UnidadeNegocio
    {
        $entidade = $empresa->entidade;
        if (! $entidade instanceof UnidadeNegocio) {
            throw new InvalidArgumentException('Empresa origem não referencia uma unidade de negócio.');
        }

        return $entidade;
    }

    private function assertEmpresaTipo(Empresa $empresa, TipoEmpresaRegistro $tipo): void
    {
        if ($empresa->tipoRegistro() !== $tipo) {
            throw new InvalidArgumentException(
                sprintf('Empresa «%d» deve ser do tipo %s.', $empresa->id, $tipo->rotulo()),
            );
        }
    }

    private function garantirPosicaoInicialSeNecessario(Estoque $estoque, int $idUnidade, int $idFruta): void
    {
        $existe = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->exists();

        if ($existe) {
            return;
        }

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => true,
        ]);
    }
}
