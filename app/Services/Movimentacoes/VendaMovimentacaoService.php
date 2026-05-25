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
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaNota;
use App\Services\Frutas\FrutaIcmsCalculoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\Movimentacoes\FrutasComEstoqueOrigem;
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
        private readonly FreteRateioMovimentacaoService $freteRateio,
        private readonly FrutaIcmsCalculoService $icmsCalculo,
        private readonly RealocacaoEstoqueHubVendaService $realocacaoEstoqueHub,
    ) {}

    /**
     * @return array{
     *     empresas_origem: Collection<int, Empresa>,
     *     empresas_destino_cliente: Collection<int, Empresa>,
     *     centros_resultado: Collection<int, UnidadeNegocio>,
     *     unidades_estoque: Collection<int, UnidadeNegocio>,
     *     unidades_hub: Collection<int, UnidadeNegocio>,
     *     frutas: Collection<int, Fruta>,
     *     frutas_catalogo: list<array{id: int, nome: string, origens: list<int>}>,
     *     fretes: Collection<int, Frete>,
     * }
     */
    public function opcoesFormularioVenda(): array
    {
        $frutas = FrutasComEstoqueOrigem::listar();

        return [
            'empresas_origem' => Empresa::query()->where('entidade_type', UnidadeNegocio::class)->with('entidade')->get()
                ->filter(fn (Empresa $e): bool => $e->entidade instanceof UnidadeNegocio
                    && ! $e->entidade->is_hub
                    && $e->entidade->emite_nota_fiscal
                    && app(UnidadeNegocioAccessService::class)->canAccess(auth()->user(), (int) $e->entidade->id))
                ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))->values(),
            'empresas_destino_cliente' => Empresa::query()->where('entidade_type', Cliente::class)->with('entidade')->get()->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))->values(),
            'centros_resultado' => UnidadeNegocio::query()
                ->where(function ($query): void {
                    $query->where('is_galpao_operacional', true)
                        ->orWhere(function ($q): void {
                            $q->where('is_hub', false)->where('possui_estoque', true);
                        });
                })
                ->permitidasPara(auth()->user())
                ->orderBy('nome')
                ->get(),
            'unidades_estoque' => UnidadeNegocio::query()
                ->where('possui_estoque', true)
                ->permitidasPara(auth()->user())
                ->orderBy('nome')
                ->get(),
            'unidades_hub' => UnidadeNegocio::query()
                ->where('is_hub', true)
                ->permitidasPara(auth()->user())
                ->orderBy('nome')
                ->get(),
            'frutas' => $frutas,
            'frutas_catalogo' => FrutasComEstoqueOrigem::catalogoJs($frutas),
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
            [$empresaOrigem, $unidadeFaturamento, $unidadeCentroResultado, $unidadePmDebito, $empresaDestino, $frete, $dataEmissao] = $this->resolverCabecalho($input);
            $custoMargem = $this->resolverCustoOperacionalMargemVenda($unidadeFaturamento, $unidadeCentroResultado, $unidadePmDebito, $input);
            $itens = $this->normalizarItens($input['itens'] ?? []);
            $numeroNf = trim((string) $input['numero_nf']);
            $observacao = $this->nullableTrim($input['observacao'] ?? null);

            /** @var VendaNota $nota */
            $nota = VendaNota::query()->create([
                'numero_nf' => $numeroNf,
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_unidade_negocio_faturamento' => $unidadeFaturamento->id,
                'id_unidade_negocio_centro_resultado' => $this->idCentroResultadoPersistido($unidadeFaturamento, $unidadeCentroResultado),
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
                    unidadeFaturamento: $unidadeFaturamento,
                    unidadeCentroResultado: $unidadeCentroResultado,
                    unidadePmDebito: $unidadePmDebito,
                    empresaDestino: $empresaDestino,
                    frete: $frete,
                    item: $item,
                    custoMargem: $custoMargem,
                    dataMovimentacao: $dataEmissao,
                    input: $input,
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

            $cabecalhoInput = array_merge([
                'numero_nf' => $ativa->vendaNota?->numero_nf,
                'id_empresa_origem' => $ativa->id_empresa_origem,
                'id_empresa_destino' => $ativa->id_empresa_destino,
                'id_unidade_negocio_centro_resultado' => $ativa->id_unidade_negocio_centro_resultado,
                'id_unidade_negocio_estoque' => $ativa->id_unidade_negocio_estoque,
                'id_frete' => $ativa->id_frete,
                'data_emissao' => $ativa->data_movimentacao,
            ], $input);

            [$empresaOrigem, $unidadeFaturamento, $unidadeCentroResultado, $unidadePmDebito, $empresaDestino, $frete, $dataEmissao] = $this->resolverCabecalho($cabecalhoInput, true);

            $custoMargem = $this->resolverCustoOperacionalMargemVenda($unidadeFaturamento, $unidadeCentroResultado, $unidadePmDebito, $input);

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
                'id_unidade_negocio_centro_resultado' => $this->idCentroResultadoPersistido($unidadeFaturamento, $unidadeCentroResultado),
                'data_emissao' => $dataEmissao,
                'observacao' => array_key_exists('observacao', $input) ? $this->nullableTrim($input['observacao']) : $nota->observacao,
            ])->save();

            $fruta = Fruta::query()->findOrFail((int) $item['id_fruta']);
            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
            $qtdUm = (float) $item['qtd_fruta_um'];
            $qtdKg = round($qtdUm * $kgPorUm, 2);
            $valorNfTotal = $this->valorRealVendaComDesconto((float) $item['valor_nf_total'], $empresaDestino);
            $valorNfUm = round($valorNfTotal / $qtdUm, 2);
            $valorNfKg = round($valorNfTotal / $qtdKg, 2);

            $this->garantirRealocacaoHubSeAplicavel($unidadeFaturamento, $unidadeCentroResultado, $unidadePmDebito, $fruta, $qtdKg, $qtdUm);

            $estoque = $this->obterOuCriarEstoqueComLock($unidadePmDebito->id, $fruta->id);
            $precoMedioKg = (float) $estoque->preco_medio_kg;
            $precoMedioUm = (float) $estoque->preco_medio_um;
            $valorCustoSaida = round($precoMedioKg * $qtdKg, 2);

            $valorCoKg = (float) $custoMargem['valor_custo_operacional'];
            $freteRateioAtual = (float) $ativa->valor_frete_rateio;

            $nova = $this->versionamento->criarNovaVersao($ativa, $this->atributosVenda([
                'venda_nota_id' => $nota->id,
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_unidade_negocio_faturamento' => $unidadeFaturamento->id,
                'id_unidade_negocio_centro_resultado' => $this->idCentroResultadoPersistido($unidadeFaturamento, $unidadeCentroResultado),
                'id_unidade_negocio_estoque' => $this->idUnidadeEstoquePersistido($unidadeFaturamento, $unidadePmDebito, $cabecalhoInput),
                'id_custo_operacional' => $custoMargem['id_custo_operacional'],
                'valor_custo_operacional' => $custoMargem['valor_custo_operacional'],
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => $qtdUm,
                'qtd_fruta_kg' => $qtdKg,
                'valor_nf_total' => $valorNfTotal,
                'valor_nf_um' => $valorNfUm,
                'valor_nf_kg' => $valorNfKg,
                'valor_custo_saida' => $valorCustoSaida,
                'valor_total_movimentacao' => $valorCustoSaida,
                'resultado_movimentacao' => $this->calcularResultadoVenda($valorNfTotal, $valorCustoSaida, $valorCoKg, $qtdKg, $freteRateioAtual),
                'id_frete' => $frete?->id,
                'saldo_estoque_fruta_kg' => (float) $estoque->qtd_fruta_kg,
                'saldo_estoque_fruta_um' => (float) $estoque->qtd_fruta_um,
                'preco_medio_fruta_kg' => $precoMedioKg,
                'preco_medio_fruta_um' => $precoMedioUm,
                'data_movimentacao' => $dataEmissao,
            ]), $this->nullableTrim($input['motivo_substituicao'] ?? null), $user);

            $this->replayVenda->reprocessarSaidasVendaNaUnidadeOrigem($unidadePmDebito->id, $fruta->id, $nova->id);
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
        $unidadeEstoque = $this->resolverUnidadeEstoqueDaVenda($venda);
        $estoque = $this->obterOuCriarEstoqueComLock($unidadeEstoque->id, (int) $venda->id_fruta);
        $posicaoAtual = $this->obterOuCriarPosicaoAtual($estoque, $unidadeEstoque->id, (int) $venda->id_fruta);

        $saldoKgNovo = round((float) $posicaoAtual->qtd_fruta_kg + (float) $venda->qtd_fruta_kg, 2);
        $saldoUmNovo = round((float) $posicaoAtual->qtd_fruta_um + (float) $venda->qtd_fruta_um, 2);
        $valorNovo = round((float) $posicaoAtual->valor_total_fruta + (float) $venda->valor_custo_saida, 2);

        $posicaoAtual->forceFill(['status_ultima_posicao' => false])->save();
        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidadeEstoque->id,
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
        $this->freteRateio->recalcular($idFrete);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function criarMovimentacaoVenda(
        VendaNota $nota,
        Empresa $empresaOrigem,
        UnidadeNegocio $unidadeFaturamento,
        UnidadeNegocio $unidadeCentroResultado,
        UnidadeNegocio $unidadePmDebito,
        Empresa $empresaDestino,
        ?Frete $frete,
        array $item,
        array $custoMargem,
        Carbon $dataMovimentacao,
        array $input,
        ?User $user,
    ): Movimentacao {
        $fruta = Fruta::query()->findOrFail((int) $item['id_fruta']);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        $qtdUm = (float) $item['qtd_fruta_um'];
        $qtdKg = round($qtdUm * $kgPorUm, 2);
        $valorNfTotal = $this->valorRealVendaComDesconto((float) $item['valor_nf_total'], $empresaDestino);
        $valorNfUm = round($valorNfTotal / $qtdUm, 2);
        $valorNfKg = round($valorNfTotal / $qtdKg, 2);

        $this->garantirRealocacaoHubSeAplicavel($unidadeFaturamento, $unidadeCentroResultado, $unidadePmDebito, $fruta, $qtdKg, $qtdUm);

        $estoque = $this->obterOuCriarEstoqueComLock($unidadePmDebito->id, $fruta->id);
        $posicaoOrigem = $this->obterOuCriarPosicaoAtual($estoque, $unidadePmDebito->id, $fruta->id);
        $precoMedioKg = (float) $estoque->preco_medio_kg;
        $precoMedioUm = (float) $estoque->preco_medio_um;
        $valorCustoSaida = round($precoMedioKg * $qtdKg, 2);
        $valorCoKg = (float) $custoMargem['valor_custo_operacional'];

        $empresaDestino->loadMissing('entidade');
        $cliente = $empresaDestino->entidade;
        if (! $cliente instanceof Cliente) {
            throw new InvalidArgumentException('Empresa destino da venda deve ser um cliente.');
        }

        $icmsVenda = $this->icmsCalculo->calcularSaidaSobreValorVenda(
            $fruta,
            $unidadeFaturamento,
            $cliente,
            $valorNfTotal,
            $qtdKg,
            $qtdUm,
            $dataMovimentacao,
        );

        $saldoKgNovo = round((float) $posicaoOrigem->qtd_fruta_kg - $qtdKg, 2);
        $saldoUmNovo = round((float) $posicaoOrigem->qtd_fruta_um - $qtdUm, 2);
        $valorAcumuladoNovo = round((float) $estoque->valor_total_acumulado - $valorCustoSaida, 2);

        $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
        $meAntes = $this->auditoria->snapshotMovimentacaoEstoque($posicaoOrigem);
        $posicaoOrigem->forceFill(['status_ultima_posicao' => false])->save();

        /** @var Movimentacao $mov */
        $mov = Movimentacao::query()->create($this->atributosVenda(array_merge([
            'valor_icms_total' => $icmsVenda['valor_icms_total'],
            'valor_icms_kg' => $icmsVenda['valor_icms_kg'],
            'valor_icms_um' => $icmsVenda['valor_icms_um'],
            'icms_convertido_kg' => $icmsVenda['icms_convertido_kg'],
        ], [
            'venda_nota_id' => $nota->id,
            'id_movimentacao_estoque_old' => $posicaoOrigem->id,
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_unidade_negocio_faturamento' => $unidadeFaturamento->id,
            'id_unidade_negocio_centro_resultado' => $this->idCentroResultadoPersistido($unidadeFaturamento, $unidadeCentroResultado),
            'id_unidade_negocio_estoque' => $this->idUnidadeEstoquePersistido($unidadeFaturamento, $unidadePmDebito, $input),
            'id_fruta' => $fruta->id,
            'qtd_fruta_um' => $qtdUm,
            'qtd_fruta_kg' => $qtdKg,
            'valor_nf_total' => $valorNfTotal,
            'valor_nf_um' => $valorNfUm,
            'valor_nf_kg' => $valorNfKg,
            'valor_custo_saida' => $valorCustoSaida,
            'valor_total_movimentacao' => $valorCustoSaida,
            'id_custo_operacional' => $custoMargem['id_custo_operacional'],
            'valor_custo_operacional' => $custoMargem['valor_custo_operacional'],
            'resultado_movimentacao' => $this->calcularResultadoVenda($valorNfTotal, $valorCustoSaida, $valorCoKg, $qtdKg, 0),
            'id_frete' => $frete?->id,
            'saldo_estoque_fruta_kg' => $saldoKgNovo,
            'saldo_estoque_fruta_um' => $saldoUmNovo,
            'preco_medio_fruta_kg' => $precoMedioKg,
            'preco_medio_fruta_um' => $precoMedioUm,
            'data_movimentacao' => $dataMovimentacao,
            'versao' => 1,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
        ])));

        $novaMe = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidadePmDebito->id,
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
     * @return array{Empresa, UnidadeNegocio, UnidadeNegocio, UnidadeNegocio, Empresa, Frete|null, Carbon}
     */
    private function resolverCabecalho(array $input, bool $usarDataInformada = false): array
    {
        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $input['id_empresa_origem']);
        $this->assertEmpresaTipo($empresaOrigem, TipoEmpresaRegistro::UNIDADE_NEGOCIO);
        $unidadeFaturamento = $this->unidadeDaEmpresa($empresaOrigem);

        if ($unidadeFaturamento->is_hub) {
            throw new InvalidArgumentException('Origem comercial não pode ser HUB. Informe a loja comercial e selecione o HUB em saída física.');
        }

        if (! $unidadeFaturamento->emite_nota_fiscal) {
            throw new InvalidArgumentException('Esta unidade não emite nota fiscal. Selecione a unidade de faturamento (ex.: Barbalha ou CD Barbalha).');
        }

        $unidadeCentroResultado = $this->resolverUnidadeCentroResultado($input, $unidadeFaturamento);
        $unidadePmDebito = $this->resolverUnidadePmDebito($input, $unidadeCentroResultado);

        $empresaDestino = Empresa::query()->with('entidade')->findOrFail((int) $input['id_empresa_destino']);
        $this->assertEmpresaTipo($empresaDestino, TipoEmpresaRegistro::CLIENTE);

        $frete = null;
        if (($input['id_frete'] ?? null) !== null && $input['id_frete'] !== '') {
            $frete = Frete::query()->whereKey((int) $input['id_frete'])->lockForUpdate()->firstOrFail();
            if ($frete->status_situacao !== FreteStatusSituacao::ABERTA->value) {
                throw new InvalidArgumentException('O frete da venda precisa estar ABERTO.');
            }
        }

        $dataEmissao = $usarDataInformada
            ? $this->resolverData($input['data_emissao'] ?? null)
            : now();

        return [$empresaOrigem, $unidadeFaturamento, $unidadeCentroResultado, $unidadePmDebito, $empresaDestino, $frete, $dataEmissao];
    }

    private function resolverUnidadeCentroResultado(array $input, UnidadeNegocio $unidadeFaturamento): UnidadeNegocio
    {
        $idCentro = $input['id_unidade_negocio_centro_resultado'] ?? null;
        if ($idCentro === null || $idCentro === '') {
            return $unidadeFaturamento;
        }

        $centro = UnidadeNegocio::query()->findOrFail((int) $idCentro);
        if ($centro->is_hub && ! $centro->is_galpao_operacional) {
            throw new InvalidArgumentException('Centro de resultado não pode ser HUB.');
        }

        return $centro;
    }

    private function resolverUnidadePmDebito(array $input, UnidadeNegocio $unidadeCentroResultado): UnidadeNegocio
    {
        $idFisica = $input['id_unidade_negocio_estoque'] ?? null;
        if ($idFisica === null || $idFisica === '') {
            return $unidadeCentroResultado;
        }

        $unidadeFisica = UnidadeNegocio::query()->findOrFail((int) $idFisica);
        if (! $unidadeFisica->possui_estoque) {
            throw new InvalidArgumentException('A unidade de saída física deve controlar estoque.');
        }

        return $unidadeFisica;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function idUnidadeEstoquePersistido(UnidadeNegocio $unidadeFaturamento, UnidadeNegocio $unidadePmDebito, array $input): ?int
    {
        $idFisica = $input['id_unidade_negocio_estoque'] ?? null;
        if ($idFisica === null || $idFisica === '') {
            return null;
        }

        return $unidadeFaturamento->id === $unidadePmDebito->id ? null : $unidadePmDebito->id;
    }

    private function idCentroResultadoPersistido(UnidadeNegocio $unidadeFaturamento, UnidadeNegocio $unidadeCentroResultado): ?int
    {
        return $unidadeFaturamento->id === $unidadeCentroResultado->id ? null : $unidadeCentroResultado->id;
    }

    private function garantirRealocacaoHubSeAplicavel(
        UnidadeNegocio $unidadeFaturamento,
        UnidadeNegocio $unidadeCentroResultado,
        UnidadeNegocio $unidadePmDebito,
        Fruta $fruta,
        float $qtdKg,
        float $qtdUm,
    ): void {
        if (! $unidadePmDebito->is_hub || $unidadeCentroResultado->id !== $unidadeFaturamento->id) {
            return;
        }

        $this->realocacaoEstoqueHub->garantirSaldoFisicoParaVenda(
            $unidadeFaturamento,
            $unidadePmDebito,
            $fruta,
            $qtdKg,
            $qtdUm,
        );
    }

    private function resolverUnidadeEstoqueDaVenda(Movimentacao $venda): UnidadeNegocio
    {
        if ($venda->id_unidade_negocio_estoque !== null) {
            return UnidadeNegocio::query()->findOrFail((int) $venda->id_unidade_negocio_estoque);
        }

        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $venda->id_empresa_origem);

        return $this->unidadeDaEmpresa($empresaOrigem);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{id_custo_operacional: int|null, valor_custo_operacional: string}
     */
    private function resolverCustoOperacionalMargemVenda(
        UnidadeNegocio $unidadeFaturamento,
        UnidadeNegocio $unidadeCentroResultado,
        UnidadeNegocio $unidadePmDebito,
        array $input,
    ): array {
        if ($unidadeCentroResultado->is_galpao_operacional) {
            return $this->snapshotCoVigenteUnidade($unidadeCentroResultado);
        }

        if ($unidadePmDebito->is_hub && $unidadeCentroResultado->id === $unidadeFaturamento->id) {
            return $this->snapshotCoVigenteUnidade($unidadeFaturamento);
        }

        if ($unidadeFaturamento->is_unidade_producao && ! $unidadePmDebito->is_hub) {
            return $this->resolverCustoOperacionalProducao($input);
        }

        return $this->coMargemZerado();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{id_custo_operacional: int|null, valor_custo_operacional: string}
     */
    private function resolverCustoOperacionalProducao(array $input): array
    {
        $aplicar = filter_var($input['aplicar_custo_operacional_hub'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (! $aplicar) {
            return $this->coMargemZerado();
        }

        $hubId = (int) ($input['id_unidade_negocio_hub_custo'] ?? 0);
        if ($hubId <= 0) {
            throw new InvalidArgumentException('Informe a unidade HUB para o custo operacional da venda.');
        }

        $hub = UnidadeNegocio::query()->findOrFail($hubId);
        if (! $hub->is_hub) {
            throw new InvalidArgumentException('A unidade selecionada para custo operacional deve ser HUB.');
        }

        $co = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $hub->id)
            ->where('status_position', true)
            ->first();

        if ($co === null) {
            throw new InvalidArgumentException('Não existe custo operacional vigente para o HUB selecionado.');
        }

        return [
            'id_custo_operacional' => (int) $co->id,
            'valor_custo_operacional' => number_format((float) $co->custo_operacional, 2, '.', ''),
        ];
    }

    /**
     * @return array{id_custo_operacional: int|null, valor_custo_operacional: string}
     */
    private function snapshotCoVigenteUnidade(UnidadeNegocio $unidade): array
    {
        $co = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('status_position', true)
            ->first();

        if ($co === null) {
            return $this->coMargemZerado();
        }

        return [
            'id_custo_operacional' => (int) $co->id,
            'valor_custo_operacional' => number_format((float) $co->custo_operacional, 2, '.', ''),
        ];
    }

    /**
     * @return array{id_custo_operacional: null, valor_custo_operacional: string}
     */
    private function coMargemZerado(): array
    {
        return [
            'id_custo_operacional' => null,
            'valor_custo_operacional' => '0.00',
        ];
    }

    private function calcularResultadoVenda(
        float $valorNfTotal,
        float $valorCustoSaida,
        float $valorCustoOperacionalKg,
        float $qtdKg,
        float $valorFreteRateio,
    ): float {
        $custoOperacionalTotal = round($valorCustoOperacionalKg * $qtdKg, 2);

        return round($valorNfTotal - $valorCustoSaida - $custoOperacionalTotal - $valorFreteRateio, 2);
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

    private function valorRealVendaComDesconto(float $valorBruto, Empresa $empresaDestino): float
    {
        $empresaDestino->loadMissing('entidade');
        $cliente = $empresaDestino->entidade;

        if (! $cliente instanceof Cliente) {
            return round($valorBruto, 2);
        }

        $percentualDesconto = min(100.0, max(0.0, (float) $cliente->desconto_nf));

        return round($valorBruto * (1 - ($percentualDesconto / 100)), 2);
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
