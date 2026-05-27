<?php

namespace App\Services\Movimentacoes;

use App\Enums\FreteStatusSituacao;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoEmpresaRegistro;
use App\Models\CategoriaMovimentacao;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Frutas\FrutaIcmsCalculoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\EmpresaEntidadeQuery;
use App\Support\Movimentacoes\CustoOperacionalSnapshot;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras e persistência exclusivas da categoria COMPRA.
 *
 * Não compartilhar com venda, transferência, devolução, etc.
 */
final class CompraMovimentacaoService
{
    private const CATEGORIA_NOME = 'COMPRA';

    public function __construct(
        private readonly MovimentacaoVersionamentoService $versionamento,
        private readonly ReplayLinhaTempoEstoqueService $replayLinhaTempoEstoque,
        private readonly FreteRateioMovimentacaoService $freteRateio,
    ) {}

    /**
     * Dados para selects do formulário web de compra (filtros alinhados às regras de {@see registrarCompra}).
     *
     * @return array{
     *     empresas_origem: Collection<int, Empresa>,
     *     empresas_destino: Collection<int, Empresa>,
     *     frutas: Collection<int, Fruta>,
     *     fretes: Collection<int, Frete>,
     * }
     */
    public function opcoesFormularioCompra(): array
    {
        $empresasOrigem = Empresa::query()
            ->where('entidade_type', Fornecedor::class)
            ->with('entidade')
            ->get()
            ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
            ->values();

        $empresasDestino = EmpresaEntidadeQuery::unidadesComEstoque()
            ->with('entidade')
            ->get()
            ->filter(fn (Empresa $e): bool => app(UnidadeNegocioAccessService::class)->canAccess(auth()->user(), (int) $e->entidade->id))
            ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
            ->values();

        $frutas = Fruta::query()
            ->where('kg_por_unidade_medicao', '>', 0)
            ->orderBy('nome')
            ->get();

        $fretes = Frete::query()
            ->where('status_situacao', FreteStatusSituacao::ABERTA->value)
            ->orderBy('nome')
            ->get();

        return [
            'empresas_origem' => $empresasOrigem,
            'empresas_destino' => $empresasDestino,
            'frutas' => $frutas,
            'fretes' => $fretes,
        ];
    }

    /**
     * @param  array{
     *     id_empresa_origem:int,
     *     id_empresa_destino:int,
     *     id_fruta:int,
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     valor_nf_total:numeric-string|float|int|string,
     *     id_frete:int,
     *     numero_nf_origem?:string|null,
     * }  $input
     */
    public function registrarCompra(array $input): Movimentacao
    {
        return DB::transaction(function () use ($input): Movimentacao {
            $categoriaId = CategoriaMovimentacao::idPorNome(self::CATEGORIA_NOME);

            $empresaOrigem = Empresa::query()->with(['entidade.estado'])->findOrFail((int) $input['id_empresa_origem']);
            $empresaDestino = Empresa::query()->with(['entidade.estado'])->findOrFail((int) $input['id_empresa_destino']);

            $this->assertEmpresaTipo($empresaOrigem, TipoEmpresaRegistro::FORNECEDOR);
            $this->assertEmpresaTipo($empresaDestino, TipoEmpresaRegistro::UNIDADE_NEGOCIO);

            $fornecedor = $empresaOrigem->entidade;
            $unidade = $empresaDestino->entidade;
            if (! $fornecedor instanceof Fornecedor || ! $unidade instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Empresa origem ou destino não resolve para fornecedor/unidade de negócio.');
            }

            if (! $unidade->possui_estoque) {
                throw new InvalidArgumentException('A unidade de destino não controla estoque.');
            }

            $fruta = Fruta::query()->findOrFail((int) $input['id_fruta']);
            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
            if ($kgPorUm <= 0) {
                throw new InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
            }

            $frete = Frete::query()->findOrFail((int) $input['id_frete']);
            if ($frete->status_situacao !== FreteStatusSituacao::ABERTA->value) {
                throw new InvalidArgumentException('Apenas fretes com situação ABERTA podem ser usados em compra.');
            }

            $qtdUm = round((float) TextoCadastro::normalizarDecimalNaoNegativo($input['qtd_fruta_um']), 2);
            if ($qtdUm <= 0) {
                throw new InvalidArgumentException('A quantidade em unidade de medição deve ser maior que zero.');
            }

            $valorNfTotal = (float) TextoCadastro::normalizarValorMonetarioBrasileiro($input['valor_nf_total']);
            if ($valorNfTotal <= 0) {
                throw new InvalidArgumentException('O valor total da NF deve ser maior que zero.');
            }

            $qtdKg = round($qtdUm * $kgPorUm, 2);
            if ($qtdKg <= 0) {
                throw new InvalidArgumentException('Quantidade em KG calculada inválida.');
            }

            $valorNfUm = round($valorNfTotal / $qtdUm, 2);
            $valorNfKg = round($valorNfTotal / $qtdKg, 2);
            $numeroNfOrigem = isset($input['numero_nf_origem']) ? trim((string) $input['numero_nf_origem']) : null;
            if ($numeroNfOrigem === '') {
                $numeroNfOrigem = null;
            }

            $dataMovimentacao = now();

            $coSnapshot = CustoOperacionalSnapshot::vigenteNaData((int) $unidade->id, $dataMovimentacao);
            if ($coSnapshot['id'] === null) {
                throw new InvalidArgumentException('Não existe custo operacional vigente (última posição) para a unidade de destino.');
            }
            $valorCo = $coSnapshot['valor'];

            $estoque = $this->obterOuCriarEstoqueComLock($unidade->id, $fruta->id);
            $this->garantirPosicaoInicialSeNecessario($estoque, $unidade->id, $fruta->id);

            $posicaoAnterior = MovimentacaoEstoque::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('id_fruta', $fruta->id)
                ->where('status_ultima_posicao', true)
                ->lockForUpdate()
                ->first();

            $saldoUmAnt = $posicaoAnterior ? (float) $posicaoAnterior->qtd_fruta_um : 0.0;
            $saldoKgAnt = $posicaoAnterior ? (float) $posicaoAnterior->qtd_fruta_kg : 0.0;

            $saldoUmNovo = round($saldoUmAnt + $qtdUm, 2);
            $saldoKgNovo = round($saldoKgAnt + $qtdKg, 2);

            $kgOutros = (float) Movimentacao::query()
                ->vigentesParaCalculo()
                ->where('categoria_movimentacao_id', $categoriaId)
                ->where('id_frete', $frete->id)
                ->sum('qtd_fruta_kg');
            $totalKgFrete = round($kgOutros + $qtdKg, 2);
            if ($totalKgFrete <= 0) {
                throw new InvalidArgumentException('Não foi possível calcular o rateio de frete (total KG).');
            }

            $valorFreteKg = round((float) $frete->valor / $totalKgFrete, 2);
            $valorFreteRateio = round($valorFreteKg * $qtdKg, 2);
            $valorFreteUm = round($valorFreteRateio / $qtdUm, 2);

            $icmsKg = (float) app(FrutaIcmsCalculoService::class)
                ->calcularEntradaPorKg($fruta, $unidade, $fornecedor, null, $dataMovimentacao);
            $icmsHistorico = $this->camposIcmsHistorico($icmsKg, $qtdKg, $qtdUm);
            $precoMedioKgLote = round($valorNfKg + $valorCo + $valorFreteKg + $icmsKg, 2);
            $precoMedioUmLote = round($precoMedioKgLote * $kgPorUm, 2);

            $Vprev = (float) $estoque->valor_total_acumulado;
            $Qprev = (float) $estoque->qtd_fruta_kg;
            $Vnovo = round($Vprev + ($precoMedioKgLote * $qtdKg), 2);
            $Qnovo = $saldoKgNovo;
            $precoConsolidadoKg = $Qnovo > 0 ? round($Vnovo / $Qnovo, 2) : 0.0;
            $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);

            $precoMedioKgLoteProvisional = $precoMedioKgLote;

            /** @var Movimentacao $movimentacao */
            $movimentacao = Movimentacao::query()->create([
                'numero_compra' => $this->proximoNumeroCompra(),
                'id_movimentacao_estoque_old' => $posicaoAnterior?->id,
                'id_movimentacao_estoque_new' => null,
                'id_empresa_origem' => $empresaOrigem->id,
                'id_empresa_destino' => $empresaDestino->id,
                'id_fruta' => $fruta->id,
                'valor_nf_total' => number_format($valorNfTotal, 2, '.', ''),
                'valor_nf_um' => number_format($valorNfUm, 2, '.', ''),
                'valor_nf_kg' => number_format($valorNfKg, 2, '.', ''),
                'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
                'id_frete' => $frete->id,
                'valor_frete_rateio' => number_format($valorFreteRateio, 2, '.', ''),
                'valor_frete_um' => number_format($valorFreteUm, 2, '.', ''),
                'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
                'id_custo_operacional' => $coSnapshot['id'],
                'valor_custo_operacional' => number_format($valorCo, 2, '.', ''),
                'saldo_estoque_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_fruta_kg' => number_format($precoMedioKgLote, 2, '.', ''),
                'preco_medio_fruta_um' => number_format($precoMedioUmLote, 2, '.', ''),
                'icms_convertido_kg' => number_format($icmsKg, 2, '.', ''),
                'valor_icms_total' => $icmsHistorico['valor_icms_total'],
                'valor_icms_kg' => $icmsHistorico['valor_icms_kg'],
                'valor_icms_um' => $icmsHistorico['valor_icms_um'],
                'categoria_movimentacao_id' => $categoriaId,
                'numero_nf_origem' => $numeroNfOrigem,
                'data_movimentacao' => $dataMovimentacao,
                'versao' => 1,
                'movimentacao_origem_id' => null,
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            ]);

            if ($posicaoAnterior !== null) {
                $posicaoAnterior->forceFill(['status_ultima_posicao' => false])->save();
            }

            $valorTotalSnapshot = round($saldoKgNovo * $precoConsolidadoKg, 2);

            $novaPosicao = MovimentacaoEstoque::query()->create([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $unidade->id,
                'id_fruta' => $fruta->id,
                'movimentacao_id' => $movimentacao->id,
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
                'valor_total_fruta' => number_format($valorTotalSnapshot, 2, '.', ''),
                'status_ultima_posicao' => true,
            ]);

            $movimentacao->forceFill(['id_movimentacao_estoque_new' => $novaPosicao->id])->save();

            $estoque->forceFill([
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
                'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
            ])->save();

            $this->recalcularRateioFreteParaTodasMovimentacoes((int) $frete->id);

            $movimentacao = $movimentacao->fresh();
            $precoMedioKgLoteFinal = (float) $movimentacao->preco_medio_fruta_kg;
            $Vfinal = round($Vnovo + (($precoMedioKgLoteFinal - $precoMedioKgLoteProvisional) * $qtdKg), 2);
            $precoConsolidadoKgFinal = $saldoKgNovo > 0 ? round($Vfinal / $saldoKgNovo, 2) : 0.0;
            $precoConsolidadoUmFinal = round($precoConsolidadoKgFinal * $kgPorUm, 2);
            $valorTotalSnapshotFinal = round($saldoKgNovo * $precoConsolidadoKgFinal, 2);

            $novaPosicao->forceFill([
                'preco_medio_kg' => number_format($precoConsolidadoKgFinal, 2, '.', ''),
                'preco_medio_um' => number_format($precoConsolidadoUmFinal, 2, '.', ''),
                'valor_total_fruta' => number_format($valorTotalSnapshotFinal, 2, '.', ''),
            ])->save();

            $estoque->forceFill([
                'preco_medio_kg' => number_format($precoConsolidadoKgFinal, 2, '.', ''),
                'preco_medio_um' => number_format($precoConsolidadoUmFinal, 2, '.', ''),
                'valor_total_acumulado' => number_format($Vfinal, 2, '.', ''),
            ])->save();

            return $movimentacao->fresh(['fruta', 'frete', 'empresaOrigem', 'empresaDestino']);
        });
    }

    /**
     * Atualiza apenas valores da NF (mesmas quantidades e vínculos) via nova versão imutável da movimentação.
     *
     * @param  array{
     *     valor_nf_total:numeric-string|float|int|string,
     *     motivo_substituicao?: string|null,
     * }  $input
     */
    public function atualizarCompra(Movimentacao $movimentacao, array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($movimentacao, $input, $user): Movimentacao {
            $motivo = isset($input['motivo_substituicao']) ? trim((string) $input['motivo_substituicao']) : null;
            if ($motivo === '') {
                $motivo = null;
            }
            unset($input['motivo_substituicao']);

            $ativa = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();
            $this->assertMovimentacaoECategoriaCompra($ativa);
            $this->versionamento->validarPodeSubstituir($ativa);

            $fruta = Fruta::query()->findOrFail($ativa->id_fruta);
            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;

            $qtdUm = (float) $ativa->qtd_fruta_um;
            $qtdKg = (float) $ativa->qtd_fruta_kg;
            if ($qtdUm <= 0 || $qtdKg <= 0) {
                throw new InvalidArgumentException('Quantidades da movimentação inválidas para atualização.');
            }

            $valorNfTotal = (float) TextoCadastro::normalizarValorMonetarioBrasileiro($input['valor_nf_total']);
            if ($valorNfTotal <= 0) {
                throw new InvalidArgumentException('O valor total da NF deve ser maior que zero.');
            }

            $empresaOrigem = Empresa::query()->with(['entidade.estado'])->findOrFail((int) $ativa->id_empresa_origem);
            $empresaDestino = Empresa::query()->with(['entidade.estado'])->findOrFail((int) $ativa->id_empresa_destino);
            $fornecedor = $empresaOrigem->entidade;
            $unidade = $empresaDestino->entidade;
            if (! $fornecedor instanceof Fornecedor || ! $unidade instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Inconsistência nas empresas vinculadas à movimentação.');
            }

            $frete = Frete::query()->findOrFail((int) $ativa->id_frete);
            if ($frete->status_situacao !== FreteStatusSituacao::ABERTA->value) {
                throw new InvalidArgumentException('Apenas fretes com situação ABERTA permitem ajuste de compra.');
            }

            $valorNfUm = round($valorNfTotal / $qtdUm, 2);
            $valorNfKg = round($valorNfTotal / $qtdKg, 2);

            $categoriaCompraId = CategoriaMovimentacao::idPorNome(self::CATEGORIA_NOME);

            $kgOutros = (float) Movimentacao::query()
                ->vigentesParaCalculo()
                ->where('categoria_movimentacao_id', $categoriaCompraId)
                ->where('id_frete', $frete->id)
                ->where('id', '!=', $ativa->id)
                ->sum('qtd_fruta_kg');
            $totalKgFrete = round($kgOutros + $qtdKg, 2);
            if ($totalKgFrete <= 0) {
                throw new InvalidArgumentException('Não foi possível calcular o rateio de frete (total KG).');
            }

            $valorFreteKg = round((float) $frete->valor / $totalKgFrete, 2);
            $valorFreteRateio = round($valorFreteKg * $qtdKg, 2);
            $valorFreteUm = round($valorFreteRateio / $qtdUm, 2);

            $valorCo = (float) $ativa->valor_custo_operacional;
            // Ajustes retroativos preservam o ICMS snapshotado na compra original.
            $icmsKg = (float) $ativa->icms_convertido_kg;
            $icmsHistorico = $this->camposIcmsHistorico($icmsKg, $qtdKg, $qtdUm);
            $precoMedioKgLoteProvisional = round($valorNfKg + $valorCo + $valorFreteKg + $icmsKg, 2);
            $precoMedioUmLoteProvisional = round($precoMedioKgLoteProvisional * $kgPorUm, 2);

            Estoque::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('id_fruta', $fruta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $novaLinha = [
                'numero_compra' => $ativa->numero_compra,
                'id_movimentacao_estoque_old' => $ativa->id_movimentacao_estoque_old,
                'id_movimentacao_estoque_new' => null,
                'id_empresa_origem' => $ativa->id_empresa_origem,
                'id_empresa_destino' => $ativa->id_empresa_destino,
                'id_fruta' => $ativa->id_fruta,
                'valor_nf_total' => number_format($valorNfTotal, 2, '.', ''),
                'valor_nf_um' => number_format($valorNfUm, 2, '.', ''),
                'valor_nf_kg' => number_format($valorNfKg, 2, '.', ''),
                'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
                'id_frete' => $ativa->id_frete,
                'valor_frete_rateio' => number_format($valorFreteRateio, 2, '.', ''),
                'valor_frete_um' => number_format($valorFreteUm, 2, '.', ''),
                'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
                'id_custo_operacional' => $ativa->id_custo_operacional,
                'valor_custo_operacional' => number_format($valorCo, 2, '.', ''),
                'saldo_estoque_fruta_kg' => number_format((float) $ativa->saldo_estoque_fruta_kg, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format((float) $ativa->saldo_estoque_fruta_um, 2, '.', ''),
                'preco_medio_fruta_kg' => number_format($precoMedioKgLoteProvisional, 2, '.', ''),
                'preco_medio_fruta_um' => number_format($precoMedioUmLoteProvisional, 2, '.', ''),
                'icms_convertido_kg' => number_format($icmsKg, 2, '.', ''),
                'valor_icms_total' => $icmsHistorico['valor_icms_total'],
                'valor_icms_kg' => $icmsHistorico['valor_icms_kg'],
                'valor_icms_um' => $icmsHistorico['valor_icms_um'],
                'categoria_movimentacao_id' => $ativa->categoria_movimentacao_id,
            ];

            $nova = $this->versionamento->criarNovaVersao($ativa, $novaLinha, $motivo, $user);

            $this->recalcularRateioFreteParaTodasMovimentacoes((int) $frete->id);
            $this->replayLinhaTempoEstoque->reprocessarUnidadeFruta($unidade->id, (int) $fruta->id);

            return $nova->fresh(['fruta', 'frete', 'empresaOrigem', 'empresaDestino']);
        });
    }

    /**
     * Recalcula rateio de frete (R$/kg) para todas as movimentações vinculadas ao frete e atualiza a trilha fiscal/custo da compra.
     *
     * Observação: posições históricas em {@see MovimentacaoEstoque} não são reencadeadas aqui;
     * apenas os registros em {@see Movimentacao} são atualizados, mantendo consistência do rateio e do preço de aquisição por lote.
     */
    public function recalcularRateioFreteParaTodasMovimentacoes(int $idFrete): void
    {
        $this->freteRateio->recalcular($idFrete);
    }

    private function assertMovimentacaoECategoriaCompra(Movimentacao $movimentacao): void
    {
        $idEsperado = CategoriaMovimentacao::idPorNome(self::CATEGORIA_NOME);
        if ((int) $movimentacao->categoria_movimentacao_id !== $idEsperado) {
            throw new InvalidArgumentException('A movimentação não é uma COMPRA.');
        }
    }

    private function proximoNumeroCompra(): int
    {
        Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacao::idPorNome(self::CATEGORIA_NOME))
            ->whereNotNull('numero_compra')
            ->orderBy('numero_compra')
            ->lockForUpdate()
            ->get(['id']);

        return ((int) Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacao::idPorNome(self::CATEGORIA_NOME))
            ->max('numero_compra')) + 1;
    }

    private function unidadeIdDestino(Movimentacao $movimentacao): int
    {
        $empresa = Empresa::query()->findOrFail((int) $movimentacao->id_empresa_destino);
        $entidade = $empresa->entidade;
        if (! $entidade instanceof UnidadeNegocio) {
            throw new InvalidArgumentException('Destino inválido.');
        }

        return $entidade->id;
    }

    private function assertEmpresaTipo(Empresa $empresa, TipoEmpresaRegistro $tipo): void
    {
        if ($empresa->tipoRegistro() !== $tipo) {
            throw new InvalidArgumentException(
                sprintf('Empresa «%d» deve ser do tipo %s.', $empresa->id, $tipo->rotulo()),
            );
        }
    }

    /**
     * @return array{valor_icms_total: string, valor_icms_kg: string, valor_icms_um: string}
     */
    private function camposIcmsHistorico(float $icmsKg, float $qtdKg, float $qtdUm): array
    {
        $total = round($icmsKg * $qtdKg, 2);

        return [
            'valor_icms_total' => number_format($total, 2, '.', ''),
            'valor_icms_kg' => number_format($icmsKg, 2, '.', ''),
            'valor_icms_um' => number_format($qtdUm > 0 ? $total / $qtdUm : 0, 2, '.', ''),
        ];
    }

    private function obterOuCriarEstoqueComLock(int $idUnidade, int $idFruta): Estoque
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque !== null) {
            return $estoque;
        }

        try {
            return Estoque::query()->create([
                'id_unidade_negocio' => $idUnidade,
                'id_fruta' => $idFruta,
                'qtd_fruta_kg' => 0,
                'qtd_fruta_um' => 0,
                'preco_medio_kg' => 0,
                'preco_medio_um' => 0,
                'valor_total_acumulado' => 0,
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque === null) {
            throw new \RuntimeException('Falha concorrente ao criar estoque.');
        }

        return $estoque;
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

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
