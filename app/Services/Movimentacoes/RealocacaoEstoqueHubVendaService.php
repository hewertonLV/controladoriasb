<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\StatusTransferenciaOperacional;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use InvalidArgumentException;

/**
 * Realoca saldo da loja comercial (faturamento) de volta ao HUB antes de toda venda com saída
 * física no HUB, revertendo transferências HUB→loja RECEBIDA_CONFORME (ADR-0060 / ADR-0061).
 */
final class RealocacaoEstoqueHubVendaService
{
    private const MOTIVO_CANCELAMENTO_REALOCACAO = 'Realocação automática para venda com saída física no HUB.';

    public function __construct(
        private readonly ReplayLinhaTempoEstoqueService $replayLinhaTempo,
    ) {}

    public function garantirSaldoFisicoParaVenda(
        UnidadeNegocio $unidadeComercial,
        UnidadeNegocio $unidadeEstoque,
        Fruta $fruta,
        float $qtdKgNecessaria,
        float $qtdUmNecessaria,
    ): void {
        if (! $unidadeEstoque->is_hub || $unidadeComercial->id === $unidadeEstoque->id) {
            return;
        }

        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        $qtdRealocKg = round($qtdKgNecessaria, 2);
        $qtdRealocUm = round($qtdUmNecessaria, 2);

        $empresaComercial = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidadeComercial->id)
            ->firstOrFail();

        $empresaHub = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidadeEstoque->id)
            ->firstOrFail();

        $entradas = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->where('status_transferencia', StatusTransferenciaOperacional::RECEBIDA_CONFORME->value)
            ->where('id_fruta', $fruta->id)
            ->where('id_empresa_destino', $empresaComercial->id)
            ->where('id_empresa_origem', $empresaHub->id)
            ->where('qtd_fruta_kg', '>', 0)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($entradas as $entrada) {
            if ($qtdRealocKg <= 0) {
                break;
            }

            $saida = Movimentacao::query()
                ->whereKey((int) $entrada->pareada_movimentacao_id)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->lockForUpdate()
                ->firstOrFail();

            $qtdTransferKg = (float) $entrada->qtd_fruta_kg;
            $qtdTransferUm = (float) $entrada->qtd_fruta_um;
            if ($qtdTransferKg <= 0 || $qtdTransferUm <= 0) {
                continue;
            }

            $qtdParcialKg = min($qtdRealocKg, $qtdTransferKg);
            $qtdParcialUm = min($qtdRealocUm, $qtdTransferUm, round($qtdParcialKg / $kgPorUm, 2));
            $qtdParcialKg = round($qtdParcialUm * $kgPorUm, 2);

            if ($qtdParcialKg <= 0 || $qtdParcialUm <= 0) {
                continue;
            }

            $this->realocarParcialTransferencia($entrada, $saida, $unidadeComercial, $unidadeEstoque, $fruta, $qtdParcialUm, $qtdParcialKg);

            $qtdRealocKg = round($qtdRealocKg - $qtdParcialKg, 2);
            $qtdRealocUm = round($qtdRealocUm - $qtdParcialUm, 2);
        }

        if ($qtdRealocKg > 0.01) {
            throw new InvalidArgumentException(
                sprintf(
                    'Não há transferência HUB→loja elegível suficiente para realocar os %.2f kg da venda com saída física no HUB.',
                    $qtdRealocKg,
                ),
            );
        }
    }

    /**
     * Desfaz a realocação HUB←loja disparada na confirmação da venda cancelada (ADR-0061 / ADR-0140).
     */
    public function reverterRealocacaoAposCancelamentoVenda(Movimentacao $venda): void
    {
        if ($venda->id_unidade_negocio_estoque === null) {
            return;
        }

        $unidadeHub = UnidadeNegocio::query()->findOrFail((int) $venda->id_unidade_negocio_estoque);
        if (! $unidadeHub->is_hub) {
            return;
        }

        $idFaturamento = (int) ($venda->id_unidade_negocio_faturamento ?? 0);
        if ($idFaturamento <= 0 || $idFaturamento === $unidadeHub->id) {
            return;
        }

        $unidadeComercial = UnidadeNegocio::query()->findOrFail($idFaturamento);
        $fruta = Fruta::query()->findOrFail((int) $venda->id_fruta);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        $qtdReverterKg = round((float) $venda->qtd_fruta_kg, 2);
        $qtdReverterUm = round((float) $venda->qtd_fruta_um, 2);

        if ($qtdReverterKg <= 0 || $qtdReverterUm <= 0) {
            return;
        }

        $empresaComercial = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidadeComercial->id)
            ->firstOrFail();

        $empresaHub = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $unidadeHub->id)
            ->firstOrFail();

        $entradas = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('id_fruta', $fruta->id)
            ->where('id_empresa_destino', $empresaComercial->id)
            ->where('id_empresa_origem', $empresaHub->id)
            ->where(function ($query): void {
                $query->where(function ($ativo): void {
                    $ativo->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                        ->where('status_transferencia', StatusTransferenciaOperacional::RECEBIDA_CONFORME->value);
                })->orWhere(function ($cancelado): void {
                    $cancelado->where('status_registro', MovimentacaoStatusRegistro::CANCELADO->value)
                        ->where('motivo_cancelamento', self::MOTIVO_CANCELAMENTO_REALOCACAO);
                });
            })
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($entradas as $entrada) {
            if ($qtdReverterKg <= 0) {
                break;
            }

            $saida = Movimentacao::query()
                ->whereKey((int) $entrada->pareada_movimentacao_id)
                ->lockForUpdate()
                ->firstOrFail();

            $qtdParcialKg = $qtdReverterKg;
            $qtdParcialUm = min($qtdReverterUm, round($qtdParcialKg / $kgPorUm, 2));
            $qtdParcialKg = round($qtdParcialUm * $kgPorUm, 2);

            if ($qtdParcialKg <= 0 || $qtdParcialUm <= 0) {
                continue;
            }

            $this->reverterParcialTransferencia(
                $entrada,
                $saida,
                $unidadeComercial,
                $unidadeHub,
                $fruta,
                $qtdParcialUm,
                $qtdParcialKg,
            );

            $qtdReverterKg = round($qtdReverterKg - $qtdParcialKg, 2);
            $qtdReverterUm = round($qtdReverterUm - $qtdParcialUm, 2);
        }
    }

    private function reverterParcialTransferencia(
        Movimentacao $entrada,
        Movimentacao $saida,
        UnidadeNegocio $unidadeComercial,
        UnidadeNegocio $unidadeHub,
        Fruta $fruta,
        float $qtdUm,
        float $qtdKg,
    ): void {
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;

        $this->restaurarQuantidadesTransferencia($entrada, $saida, $qtdUm, $qtdKg, $kgPorUm);

        $this->replayLinhaTempo->reprocessarUnidadeFruta($unidadeComercial->id, $fruta->id);
        $this->replayLinhaTempo->reprocessarUnidadeFruta($unidadeHub->id, $fruta->id);
    }

    private function realocarParcialTransferencia(
        Movimentacao $entrada,
        Movimentacao $saida,
        UnidadeNegocio $unidadeComercial,
        UnidadeNegocio $unidadeHub,
        Fruta $fruta,
        float $qtdUm,
        float $qtdKg,
    ): void {
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        $precoEntradaKg = (float) $entrada->preco_medio_fruta_kg;
        $precoHubKg = (float) $saida->preco_medio_fruta_kg;

        $this->debitarEstoqueUnidade($unidadeComercial->id, $fruta->id, $qtdUm, $qtdKg, $kgPorUm, $precoEntradaKg);
        $this->creditarEstoqueHubSemCo($unidadeHub->id, $fruta->id, $qtdUm, $qtdKg, $kgPorUm, $precoHubKg);

        $this->reduzirQuantidadesTransferencia($entrada, $saida, $qtdUm, $qtdKg, $kgPorUm);

        $this->replayLinhaTempo->reprocessarUnidadeFruta($unidadeComercial->id, $fruta->id);
        $this->replayLinhaTempo->reprocessarUnidadeFruta($unidadeHub->id, $fruta->id);
    }

    private function reduzirQuantidadesTransferencia(
        Movimentacao $entrada,
        Movimentacao $saida,
        float $qtdUm,
        float $qtdKg,
        float $kgPorUm,
    ): void {
        $novaQtdUmEntrada = round((float) $entrada->qtd_fruta_um - $qtdUm, 2);
        $novaQtdKgEntrada = round((float) $entrada->qtd_fruta_kg - $qtdKg, 2);
        $novaQtdRecUm = round(max(0, (float) $entrada->qtd_recebida_um - $qtdUm), 2);
        $novaQtdRecKg = round(max(0, (float) $entrada->qtd_recebida_kg - $qtdKg), 2);

        $novaQtdUmSaida = round((float) $saida->qtd_fruta_um - $qtdUm, 2);
        $novaQtdKgSaida = round((float) $saida->qtd_fruta_kg - $qtdKg, 2);

        if ($novaQtdKgEntrada <= 0 || $novaQtdUmEntrada <= 0) {
            $agora = now();
            $motivo = self::MOTIVO_CANCELAMENTO_REALOCACAO;

            $entrada->forceFill([
                'qtd_fruta_um' => '0.00',
                'qtd_fruta_kg' => '0.00',
                'qtd_recebida_um' => '0.00',
                'qtd_recebida_kg' => '0.00',
                'id_movimentacao_estoque_old' => null,
                'id_movimentacao_estoque_new' => null,
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'status_transferencia' => StatusTransferenciaOperacional::CANCELADA->value,
                'motivo_cancelamento' => $motivo,
                'cancelada_em' => $agora,
            ])->saveQuietly();

            $saida->forceFill([
                'qtd_fruta_um' => '0.00',
                'qtd_fruta_kg' => '0.00',
                'id_movimentacao_estoque_old' => null,
                'id_movimentacao_estoque_new' => null,
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'status_transferencia' => StatusTransferenciaOperacional::CANCELADA->value,
                'motivo_cancelamento' => $motivo,
                'cancelada_em' => $agora,
            ])->saveQuietly();

            return;
        }

        $precoEntradaKg = (float) $entrada->preco_medio_fruta_kg;
        $valorEntradaTotal = round($precoEntradaKg * $novaQtdKgEntrada, 2);
        $precoEntradaUm = round($precoEntradaKg * $kgPorUm, 2);

        $precoSaidaKg = (float) $saida->preco_medio_fruta_kg;
        $valorSaidaTotal = round($precoSaidaKg * $novaQtdKgSaida, 2);
        $precoSaidaUm = round($precoSaidaKg * $kgPorUm, 2);

        $entrada->forceFill([
            'qtd_fruta_um' => number_format($novaQtdUmEntrada, 2, '.', ''),
            'qtd_fruta_kg' => number_format($novaQtdKgEntrada, 2, '.', ''),
            'qtd_recebida_um' => number_format($novaQtdRecUm, 2, '.', ''),
            'qtd_recebida_kg' => number_format($novaQtdRecKg, 2, '.', ''),
            'valor_nf_total' => number_format($valorEntradaTotal, 2, '.', ''),
            'valor_nf_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'valor_nf_um' => number_format($novaQtdUmEntrada > 0 ? $valorEntradaTotal / $novaQtdUmEntrada : 0, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoEntradaUm, 2, '.', ''),
        ])->saveQuietly();

        $saida->forceFill([
            'qtd_fruta_um' => number_format($novaQtdUmSaida, 2, '.', ''),
            'qtd_fruta_kg' => number_format($novaQtdKgSaida, 2, '.', ''),
            'valor_nf_total' => number_format($valorSaidaTotal, 2, '.', ''),
            'valor_nf_kg' => number_format($precoSaidaKg, 2, '.', ''),
            'valor_nf_um' => number_format($novaQtdUmSaida > 0 ? $valorSaidaTotal / $novaQtdUmSaida : 0, 2, '.', ''),
        ])->saveQuietly();
    }

    private function restaurarQuantidadesTransferencia(
        Movimentacao $entrada,
        Movimentacao $saida,
        float $qtdUm,
        float $qtdKg,
        float $kgPorUm,
    ): void {
        if ($entrada->status_registro === MovimentacaoStatusRegistro::CANCELADO->value) {
            $entrada->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
                'status_transferencia' => StatusTransferenciaOperacional::RECEBIDA_CONFORME->value,
                'motivo_cancelamento' => null,
                'cancelada_em' => null,
            ])->saveQuietly();

            $saida->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
                'status_transferencia' => StatusTransferenciaOperacional::RECEBIDA_CONFORME->value,
                'motivo_cancelamento' => null,
                'cancelada_em' => null,
            ])->saveQuietly();
        }

        $novaQtdUmEntrada = round((float) $entrada->qtd_fruta_um + $qtdUm, 2);
        $novaQtdKgEntrada = round((float) $entrada->qtd_fruta_kg + $qtdKg, 2);
        $novaQtdRecUm = round((float) $entrada->qtd_recebida_um + $qtdUm, 2);
        $novaQtdRecKg = round((float) $entrada->qtd_recebida_kg + $qtdKg, 2);

        $novaQtdUmSaida = round((float) $saida->qtd_fruta_um + $qtdUm, 2);
        $novaQtdKgSaida = round((float) $saida->qtd_fruta_kg + $qtdKg, 2);

        $precoEntradaKg = (float) $entrada->preco_medio_fruta_kg;
        $valorEntradaTotal = round($precoEntradaKg * $novaQtdKgEntrada, 2);
        $precoEntradaUm = round($precoEntradaKg * $kgPorUm, 2);

        $precoSaidaKg = (float) $saida->preco_medio_fruta_kg;
        $valorSaidaTotal = round($precoSaidaKg * $novaQtdKgSaida, 2);
        $precoSaidaUm = round($precoSaidaKg * $kgPorUm, 2);

        $entrada->forceFill([
            'qtd_fruta_um' => number_format($novaQtdUmEntrada, 2, '.', ''),
            'qtd_fruta_kg' => number_format($novaQtdKgEntrada, 2, '.', ''),
            'qtd_recebida_um' => number_format($novaQtdRecUm, 2, '.', ''),
            'qtd_recebida_kg' => number_format($novaQtdRecKg, 2, '.', ''),
            'valor_nf_total' => number_format($valorEntradaTotal, 2, '.', ''),
            'valor_nf_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'valor_nf_um' => number_format($novaQtdUmEntrada > 0 ? $valorEntradaTotal / $novaQtdUmEntrada : 0, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoEntradaUm, 2, '.', ''),
        ])->saveQuietly();

        $saida->forceFill([
            'qtd_fruta_um' => number_format($novaQtdUmSaida, 2, '.', ''),
            'qtd_fruta_kg' => number_format($novaQtdKgSaida, 2, '.', ''),
            'valor_nf_total' => number_format($valorSaidaTotal, 2, '.', ''),
            'valor_nf_kg' => number_format($precoSaidaKg, 2, '.', ''),
            'valor_nf_um' => number_format($novaQtdUmSaida > 0 ? $valorSaidaTotal / $novaQtdUmSaida : 0, 2, '.', ''),
        ])->saveQuietly();
    }

    private function debitarEstoqueUnidade(int $idUnidade, int $idFruta, float $qtdUm, float $qtdKg, float $kgPorUm, float $precoDebitoKg): void
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->firstOrFail();

        $posicao = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->firstOrFail();

        $saldoUmAnt = (float) $posicao->qtd_fruta_um;
        $saldoKgAnt = (float) $posicao->qtd_fruta_kg;
        $Vprev = (float) $estoque->valor_total_acumulado;

        $saldoUmNovo = round($saldoUmAnt - $qtdUm, 2);
        $saldoKgNovo = round($saldoKgAnt - $qtdKg, 2);
        $Vnovo = round($Vprev - ($precoDebitoKg * $qtdKg), 2);
        $precoConsolidadoKg = $saldoKgNovo > 0 ? round($Vnovo / $saldoKgNovo, 2) : 0.0;
        $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);

        $posicao->forceFill(['status_ultima_posicao' => false])->save();

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_fruta' => number_format($saldoKgNovo * $precoConsolidadoKg, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
        ])->save();
    }

    /**
     * Entrada no HUB ao preço de saída original da transferência — sem incrementar CO do HUB.
     */
    private function creditarEstoqueHubSemCo(
        int $idUnidadeHub,
        int $idFruta,
        float $qtdUm,
        float $qtdKg,
        float $kgPorUm,
        float $precoHubKg,
    ): void {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidadeHub)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque === null) {
            $estoque = Estoque::query()->create([
                'id_unidade_negocio' => $idUnidadeHub,
                'id_fruta' => $idFruta,
                'qtd_fruta_kg' => '0.00',
                'qtd_fruta_um' => '0.00',
                'preco_medio_kg' => '0.00',
                'preco_medio_um' => '0.00',
                'valor_total_acumulado' => '0.00',
            ]);
        }

        $posicao = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidadeHub)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->first();

        if ($posicao === null) {
            $posicao = MovimentacaoEstoque::query()->create([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $idUnidadeHub,
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

        $saldoUmAnt = (float) $posicao->qtd_fruta_um;
        $saldoKgAnt = (float) $posicao->qtd_fruta_kg;
        $Vprev = (float) $estoque->valor_total_acumulado;

        $Vlote = round($precoHubKg * $qtdKg, 2);
        $saldoUmNovo = round($saldoUmAnt + $qtdUm, 2);
        $saldoKgNovo = round($saldoKgAnt + $qtdKg, 2);
        $Vnovo = round($Vprev + $Vlote, 2);
        $precoConsolidadoKg = $saldoKgNovo > 0 ? round($Vnovo / $saldoKgNovo, 2) : 0.0;
        $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);

        $posicao->forceFill(['status_ultima_posicao' => false])->save();

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidadeHub,
            'id_fruta' => $idFruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_fruta' => number_format($Vnovo, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
        ])->save();
    }
}
