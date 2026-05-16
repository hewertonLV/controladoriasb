<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasVendaOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoEmpresaRegistro;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaNota;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class VendaMovimentacaoService
{
    public function __construct(
        private readonly MovimentacaoVersionamentoService $versionamento,
        private readonly ReprocessaSaidasVendaOrigem $replayVenda,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    /**
     * @return array{
     *     empresas_origem: Collection<int, Empresa>,
     *     empresas_destino_cliente: Collection<int, Empresa>,
     *     unidades_faturamento: Collection<int, UnidadeNegocio>,
     *     frutas: Collection<int, Fruta>,
     *     fretes: Collection<int, Frete>,
     * }
     */
    public function opcoesFormularioVenda(): array
    {
        return [
            'empresas_origem' => Empresa::query()->where('entidade_type', UnidadeNegocio::class)->with('entidade')->get()->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))->values(),
            'empresas_destino_cliente' => Empresa::query()->where('entidade_type', Cliente::class)->with('entidade')->get()->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))->values(),
            'unidades_faturamento' => UnidadeNegocio::query()->where('is_hub', false)->orderBy('nome')->get(),
            'frutas' => Fruta::query()->where('kg_por_unidade_medicao', '>', 0)->orderBy('nome')->get(),
            'fretes' => Frete::query()->where('status_situacao', FreteStatusSituacao::ABERTA->value)->orderBy('nome')->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{nota: VendaNota, movimentacoes: Collection<int, Movimentacao>}
     */
    public function registrarVenda(array $input, ?User $user = null): array
    {
        return DB::transaction(function () use ($input, $user): array {
            [$empresaOrigem, $unidadeOrigem, $empresaDestino, $unidadeFaturamento, $frete, $dataEmissao] = $this->resolverCabecalho($input);
            $itens = $this->normalizarItens($input['itens'] ?? []);
            $numeroNf = trim((string) $input['numero_nf']);
            $observacao = $this->nullableTrim($input['observacao'] ?? null);

            /** @var VendaNota $nota */
            $nota = VendaNota::query()->create([
                'numero_nf' => $numeroNf,
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_unidade_negocio_faturamento' => $unidadeFaturamento->id,
                'data_emissao' => $dataEmissao,
                'valor_total_nf' => '0.00',
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
                'observacao' => $observacao,
            ]);

            $movimentacoes = collect();
            foreach ($itens as $item) {
                $movimentacoes->push($this->criarMovimentacaoVenda(
                    nota: $nota,
                    empresaOrigem: $empresaOrigem,
                    unidadeOrigem: $unidadeOrigem,
                    empresaDestino: $empresaDestino,
                    unidadeFaturamento: $unidadeFaturamento,
                    frete: $frete,
                    item: $item,
                    dataMovimentacao: $dataEmissao,
                    user: $user,
                ));
            }

            $this->atualizarValorTotalNota($nota);
            if ($frete !== null) {
                $this->recalcularRateioFreteParaVendas($frete->id);
            }

            return ['nota' => $nota->fresh(), 'movimentacoes' => $movimentacoes->map->fresh()];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function atualizarVenda(Movimentacao $movimentacao, array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($movimentacao, $input, $user): Movimentacao {
            $ativa = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();
            $this->assertVendaSaidaAtiva($ativa);
            $this->versionamento->validarPodeSubstituir($ativa);

            [$empresaOrigem, $unidadeOrigem, $empresaDestino, $unidadeFaturamento, $frete, $dataEmissao] = $this->resolverCabecalho(array_merge([
                'numero_nf' => $ativa->vendaNota?->numero_nf,
                'id_empresa_origem' => $ativa->id_empresa_origem,
                'id_empresa_destino' => $ativa->id_empresa_destino,
                'id_unidade_negocio_faturamento' => $ativa->id_unidade_negocio_faturamento,
                'id_frete' => $ativa->id_frete,
                'data_emissao' => $ativa->data_movimentacao,
            ], $input));

            $item = $this->normalizarItem([
                'id_fruta' => $input['id_fruta'] ?? $ativa->id_fruta,
                'qtd_fruta_um' => $input['qtd_fruta_um'],
                'valor_nf_total' => $input['valor_nf_total'],
            ]);

            $nota = VendaNota::query()->whereKey((int) $ativa->venda_nota_id)->lockForUpdate()->firstOrFail();
            $nota->forceFill([
                'numero_nf' => trim((string) ($input['numero_nf'] ?? $nota->numero_nf)),
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_unidade_negocio_faturamento' => $unidadeFaturamento->id,
                'data_emissao' => $dataEmissao,
                'observacao' => array_key_exists('observacao', $input) ? $this->nullableTrim($input['observacao']) : $nota->observacao,
            ])->save();

            $fruta = Fruta::query()->findOrFail((int) $item['id_fruta']);
            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
            $qtdUm = (float) $item['qtd_fruta_um'];
            $qtdKg = round($qtdUm * $kgPorUm, 2);
            $valorNfTotal = (float) $item['valor_nf_total'];
            $valorNfUm = round($valorNfTotal / $qtdUm, 2);
            $valorNfKg = round($valorNfTotal / $qtdKg, 2);

            $estoque = $this->obterOuCriarEstoqueComLock($unidadeOrigem->id, $fruta->id);
            $precoMedioKg = (float) $estoque->preco_medio_kg;
            $precoMedioUm = (float) $estoque->preco_medio_um;
            $valorCustoSaida = round($precoMedioKg * $qtdKg, 2);

            $nova = $this->versionamento->criarNovaVersao($ativa, $this->atributosVenda([
                'venda_nota_id' => $nota->id,
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_unidade_negocio_faturamento' => $unidadeFaturamento->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => $qtdUm,
                'qtd_fruta_kg' => $qtdKg,
                'valor_nf_total' => $valorNfTotal,
                'valor_nf_um' => $valorNfUm,
                'valor_nf_kg' => $valorNfKg,
                'valor_custo_saida' => $valorCustoSaida,
                'valor_total_movimentacao' => $valorCustoSaida,
                'resultado_movimentacao' => round($valorNfTotal - $valorCustoSaida, 2),
                'id_frete' => $frete?->id,
                'saldo_estoque_fruta_kg' => (float) $estoque->qtd_fruta_kg,
                'saldo_estoque_fruta_um' => (float) $estoque->qtd_fruta_um,
                'preco_medio_fruta_kg' => $precoMedioKg,
                'preco_medio_fruta_um' => $precoMedioUm,
                'data_movimentacao' => $dataEmissao,
            ]), $this->nullableTrim($input['motivo_substituicao'] ?? null), $user);

            $this->replayVenda->reprocessarSaidasVendaNaUnidadeOrigem($unidadeOrigem->id, $fruta->id, $nova->id);
            $this->atualizarValorTotalNota($nota);
            if ($ativa->id_frete !== null) {
                $this->recalcularRateioFreteParaVendas((int) $ativa->id_frete);
            }
            if ($frete !== null) {
                $this->recalcularRateioFreteParaVendas($frete->id);
            }

            return $nova->fresh(['vendaNota', 'fruta', 'empresaOrigem', 'empresaDestino']);
        });
    }

    public function estornarVendaNoEstoqueOrigem(Movimentacao $venda): void
    {
        $this->assertVendaSaida($venda);
        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $venda->id_empresa_origem);
        $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
        $estoque = $this->obterOuCriarEstoqueComLock($unidadeOrigem->id, (int) $venda->id_fruta);
        $posicaoAtual = $this->obterOuCriarPosicaoAtual($estoque, $unidadeOrigem->id, (int) $venda->id_fruta);

        $saldoKgNovo = round((float) $posicaoAtual->qtd_fruta_kg + (float) $venda->qtd_fruta_kg, 2);
        $saldoUmNovo = round((float) $posicaoAtual->qtd_fruta_um + (float) $venda->qtd_fruta_um, 2);
        $valorNovo = round((float) $posicaoAtual->valor_total_fruta + (float) $venda->valor_custo_saida, 2);

        $posicaoAtual->forceFill(['status_ultima_posicao' => false])->save();
        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $venda->id_fruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => (string) $venda->preco_medio_fruta_kg,
            'preco_medio_um' => (string) $venda->preco_medio_fruta_um,
            'valor_total_fruta' => number_format($valorNovo, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => (string) $venda->preco_medio_fruta_kg,
            'preco_medio_um' => (string) $venda->preco_medio_fruta_um,
            'valor_total_acumulado' => number_format($valorNovo, 2, '.', ''),
        ])->save();
    }

    public function recalcularRateioFreteParaVendas(int $idFrete): void
    {
        DB::transaction(function () use ($idFrete): void {
            $frete = Frete::query()->whereKey($idFrete)->lockForUpdate()->first();
            if ($frete === null) {
                return;
            }

            $movs = Movimentacao::query()
                ->vigentesParaCalculo()
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
                ->where('id_frete', $idFrete)
                ->orderBy('data_movimentacao')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($movs->isEmpty()) {
                $frete->forceFill(['valor_fruta_kg' => '0.00'])->save();

                return;
            }

            $totalKg = round((float) $movs->sum(static fn (Movimentacao $m): float => (float) $m->qtd_fruta_kg), 2);
            if ($totalKg <= 0) {
                return;
            }

            $valorFreteKg = round((float) $frete->valor / $totalKg, 2);
            foreach ($movs as $m) {
                $qtdKg = (float) $m->qtd_fruta_kg;
                $qtdUm = (float) $m->qtd_fruta_um;
                $rateio = round($valorFreteKg * $qtdKg, 2);
                $freteUm = $qtdUm > 0 ? round($rateio / $qtdUm, 2) : 0.0;

                $m->forceFill([
                    'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
                    'valor_frete_rateio' => number_format($rateio, 2, '.', ''),
                    'valor_frete_um' => number_format($freteUm, 2, '.', ''),
                    'resultado_movimentacao' => number_format(round((float) $m->valor_nf_total - (float) $m->valor_custo_saida - $rateio, 2), 2, '.', ''),
                ])->saveQuietly();
            }

            $frete->forceFill(['valor_fruta_kg' => number_format($valorFreteKg, 2, '.', '')])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function criarMovimentacaoVenda(
        VendaNota $nota,
        Empresa $empresaOrigem,
        UnidadeNegocio $unidadeOrigem,
        Empresa $empresaDestino,
        UnidadeNegocio $unidadeFaturamento,
        ?Frete $frete,
        array $item,
        Carbon $dataMovimentacao,
        ?User $user,
    ): Movimentacao {
        $fruta = Fruta::query()->findOrFail((int) $item['id_fruta']);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        $qtdUm = (float) $item['qtd_fruta_um'];
        $qtdKg = round($qtdUm * $kgPorUm, 2);
        $valorNfTotal = (float) $item['valor_nf_total'];
        $valorNfUm = round($valorNfTotal / $qtdUm, 2);
        $valorNfKg = round($valorNfTotal / $qtdKg, 2);

        $estoque = $this->obterOuCriarEstoqueComLock($unidadeOrigem->id, $fruta->id);
        $posicaoOrigem = $this->obterOuCriarPosicaoAtual($estoque, $unidadeOrigem->id, $fruta->id);
        $precoMedioKg = (float) $estoque->preco_medio_kg;
        $precoMedioUm = (float) $estoque->preco_medio_um;
        $valorCustoSaida = round($precoMedioKg * $qtdKg, 2);
        $saldoKgNovo = round((float) $posicaoOrigem->qtd_fruta_kg - $qtdKg, 2);
        $saldoUmNovo = round((float) $posicaoOrigem->qtd_fruta_um - $qtdUm, 2);
        $valorAcumuladoNovo = round((float) $estoque->valor_total_acumulado - $valorCustoSaida, 2);

        $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
        $meAntes = $this->auditoria->snapshotMovimentacaoEstoque($posicaoOrigem);
        $posicaoOrigem->forceFill(['status_ultima_posicao' => false])->save();

        /** @var Movimentacao $mov */
        $mov = Movimentacao::query()->create($this->atributosVenda([
            'venda_nota_id' => $nota->id,
            'id_movimentacao_estoque_old' => $posicaoOrigem->id,
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_unidade_negocio_faturamento' => $unidadeFaturamento->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'qtd_fruta_kg' => $qtdKg,
            'valor_nf_total' => $valorNfTotal,
            'valor_nf_um' => $valorNfUm,
            'valor_nf_kg' => $valorNfKg,
            'valor_custo_saida' => $valorCustoSaida,
            'valor_total_movimentacao' => $valorCustoSaida,
            'resultado_movimentacao' => round($valorNfTotal - $valorCustoSaida, 2),
            'id_frete' => $frete?->id,
            'saldo_estoque_fruta_kg' => $saldoKgNovo,
            'saldo_estoque_fruta_um' => $saldoUmNovo,
            'preco_medio_fruta_kg' => $precoMedioKg,
            'preco_medio_fruta_um' => $precoMedioUm,
            'data_movimentacao' => $dataMovimentacao,
            'versao' => 1,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
        ]));

        $novaMe = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => $mov->id,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorAcumuladoNovo, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $mov->forceFill(['id_movimentacao_estoque_new' => $novaMe->id])->saveQuietly();
        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($valorAcumuladoNovo, 2, '.', ''),
        ])->save();

        $this->auditoria->registrarRegistroVenda($mov->fresh(), $user, $estoqueAntes, $this->auditoria->snapshotEstoque($estoque->fresh()), $meAntes, $this->auditoria->snapshotMovimentacaoEstoque($novaMe->fresh()));

        return $mov->fresh();
    }

    /**
     * @return array{Empresa, UnidadeNegocio, Empresa, UnidadeNegocio, Frete|null, Carbon}
     */
    private function resolverCabecalho(array $input): array
    {
        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $input['id_empresa_origem']);
        $this->assertEmpresaTipo($empresaOrigem, TipoEmpresaRegistro::UNIDADE_NEGOCIO);
        $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);

        $empresaDestino = Empresa::query()->with('entidade')->findOrFail((int) $input['id_empresa_destino']);
        $this->assertEmpresaTipo($empresaDestino, TipoEmpresaRegistro::CLIENTE);

        $unidadeFaturamento = UnidadeNegocio::query()->findOrFail((int) $input['id_unidade_negocio_faturamento']);
        if ($unidadeFaturamento->is_hub) {
            throw new InvalidArgumentException('A unidade de faturamento não pode ser HUB.');
        }

        $frete = null;
        if (($input['id_frete'] ?? null) !== null && $input['id_frete'] !== '') {
            $frete = Frete::query()->whereKey((int) $input['id_frete'])->lockForUpdate()->firstOrFail();
            if ($frete->status_situacao !== FreteStatusSituacao::ABERTA->value) {
                throw new InvalidArgumentException('O frete da venda precisa estar ABERTO.');
            }
        }

        return [$empresaOrigem, $unidadeOrigem, $empresaDestino, $unidadeFaturamento, $frete, $this->resolverData($input['data_emissao'] ?? null)];
    }

    /**
     * @return array<int, array{id_fruta:int, qtd_fruta_um:string, valor_nf_total:string}>
     */
    private function normalizarItens(mixed $itens): array
    {
        if (! is_array($itens) || $itens === []) {
            throw new InvalidArgumentException('Informe ao menos um item de venda.');
        }

        return array_map(fn (array $item): array => $this->normalizarItem($item), $itens);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id_fruta:int, qtd_fruta_um:string, valor_nf_total:string}
     */
    private function normalizarItem(array $item): array
    {
        $fruta = Fruta::query()->findOrFail((int) $item['id_fruta']);
        if ((float) $fruta->kg_por_unidade_medicao <= 0) {
            throw new InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
        }

        $qtdRaw = $item['qtd_fruta_um'];
        $qtdUm = is_string($qtdRaw) && str_contains($qtdRaw, ',')
            ? TextoCadastro::normalizarDecimalNaoNegativo($qtdRaw)
            : number_format(max(0, (float) $qtdRaw), 2, '.', '');
        if ((float) $qtdUm <= 0) {
            throw new InvalidArgumentException('Quantidade de item inválida.');
        }

        $valorRaw = $item['valor_nf_total'];
        $valorNfTotal = is_string($valorRaw) && str_contains($valorRaw, ',')
            ? TextoCadastro::normalizarValorMonetarioBrasileiro($valorRaw)
            : number_format(max(0, (float) $valorRaw), 2, '.', '');
        if ((float) $valorNfTotal < 0) {
            throw new InvalidArgumentException('Valor de venda inválido.');
        }

        return ['id_fruta' => (int) $fruta->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function atributosVenda(array $attrs): array
    {
        return array_merge([
            'id_movimentacao_estoque_new' => null,
            'valor_icms_total' => '0.00',
            'valor_icms_kg' => '0.00',
            'valor_icms_um' => '0.00',
            'id_custo_operacional' => null,
            'valor_custo_operacional' => '0.00',
            'icms_convertido_kg' => '0.00',
            'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Venda->value,
            'status_movimentacao_id' => StatusMovimentacao::ID_SAIDA,
            'status_transferencia' => null,
            'transferencia_origem_id' => null,
            'pareada_movimentacao_id' => null,
            'numero_nf_origem' => null,
            'numero_nf_destino' => null,
            'qtd_recebida_um' => null,
            'qtd_recebida_kg' => null,
            'status_recebimento' => null,
            'observacao_recebimento' => null,
            'motivo_doacao' => null,
            'categoria_descarte_id' => null,
            'motivo_descarte' => null,
            'versao_replay' => 1,
            'movimentacao_origem_id' => null,
            'substituida_por_id' => null,
            'substituida_em' => null,
            'motivo_substituicao' => null,
            'valor_frete_rateio' => '0.00',
            'valor_frete_um' => '0.00',
            'valor_frete_kg' => '0.00',
        ], $attrs);
    }

    private function atualizarValorTotalNota(VendaNota $nota): void
    {
        $total = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('venda_nota_id', $nota->id)
            ->sum('valor_nf_total');

        $nota->forceFill(['valor_total_nf' => number_format((float) $total, 2, '.', '')])->save();
    }

    private function obterOuCriarEstoqueComLock(int $idUnidade, int $idFruta): Estoque
    {
        $estoque = Estoque::query()->where('id_unidade_negocio', $idUnidade)->where('id_fruta', $idFruta)->lockForUpdate()->first();
        if ($estoque !== null) {
            return $estoque;
        }

        return Estoque::query()->create([
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_acumulado' => '0.00',
        ]);
    }

    private function obterOuCriarPosicaoAtual(Estoque $estoque, int $idUnidade, int $idFruta): MovimentacaoEstoque
    {
        $posicao = MovimentacaoEstoque::query()->where('id_unidade_negocio', $idUnidade)->where('id_fruta', $idFruta)->where('status_ultima_posicao', true)->lockForUpdate()->first();
        if ($posicao !== null) {
            return $posicao;
        }

        return MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => (string) $estoque->qtd_fruta_kg,
            'qtd_fruta_um' => (string) $estoque->qtd_fruta_um,
            'preco_medio_kg' => (string) $estoque->preco_medio_kg,
            'preco_medio_um' => (string) $estoque->preco_medio_um,
            'valor_total_fruta' => (string) $estoque->valor_total_acumulado,
            'status_ultima_posicao' => true,
        ]);
    }

    private function assertVendaSaidaAtiva(Movimentacao $m): void
    {
        $this->assertVendaSaida($m);
        if ($m->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
            throw new InvalidArgumentException('Somente versões ativas podem ser atualizadas.');
        }
    }

    private function assertVendaSaida(Movimentacao $m): void
    {
        if ((int) $m->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Venda->value) {
            throw new InvalidArgumentException('Somente movimentações da categoria VENDA.');
        }
        if ((int) $m->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
            throw new InvalidArgumentException('Somente saídas de venda podem ser alteradas por este fluxo.');
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
            throw new InvalidArgumentException(sprintf('Empresa «%d» deve ser do tipo %s.', $empresa->id, $tipo->rotulo()));
        }
    }

    private function resolverData(mixed $raw): Carbon
    {
        return $raw === null || $raw === '' ? now() : Carbon::parse((string) $raw);
    }

    private function nullableTrim(mixed $raw): ?string
    {
        $value = $raw === null ? null : trim((string) $raw);

        return $value === '' ? null : $value;
    }
}
