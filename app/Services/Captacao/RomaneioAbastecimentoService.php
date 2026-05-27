<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRomaneioManualLinha;
use App\Models\Captacao\Pedido;
use App\Models\Estoque;
use App\Models\Fruta;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class RomaneioAbastecimentoService
{
    /**
     * @return Collection<int, array{
     *     id_fruta: int,
     *     id_cigam: string,
     *     fruta_nome: string,
     *     unidade_medicao: string,
     *     demanda_kg: string,
     *     demanda_um: string,
     *     estoque_kg: string,
     *     estoque_um: string,
     *     a_receber_kg: string,
     *     a_receber_um: string,
     *     demanda_kg_formatado: string,
     *     demanda_um_formatado: string,
     *     estoque_kg_formatado: string,
     *     estoque_um_formatado: string,
     *     a_receber_kg_formatado: string,
     *     a_receber_um_formatado: string,
     * }>
     */
    public function preview(CaptacaoLote $lote): Collection
    {
        $pedidos = $this->pedidosDoLote($lote);

        /** @var array<int, array{um: float, kg: float}> $demandaGalpaoPorFruta */
        $demandaGalpaoPorFruta = [];

        foreach ($pedidos as $pedido) {
            if (! $this->pedidoContaParaAbastecimentoGalpao($pedido, $lote)) {
                continue;
            }

            foreach ($pedido->itens as $item) {
                $this->acumularDemandaFruta($demandaGalpaoPorFruta, $item->id_fruta, (float) $item->quantidade, (float) $item->fruta->kg_por_unidade_medicao);
            }
        }

        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            $linhasManuais = CaptacaoRomaneioManualLinha::query()
                ->with('fruta:id,nome,unidade_medicao,kg_por_unidade_medicao')
                ->where('id_captacao_lote', $lote->id)
                ->get();

            foreach ($linhasManuais as $linha) {
                $this->acumularDemandaFruta(
                    $demandaGalpaoPorFruta,
                    $linha->id_fruta,
                    (float) $linha->quantidade,
                    (float) $linha->fruta->kg_por_unidade_medicao,
                );
            }
        }

        $frutasPorId = $pedidos->flatMap->itens
            ->mapWithKeys(fn ($item) => [(int) $item->id_fruta => $item->fruta])
            ->all();

        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            foreach (CaptacaoRomaneioManualLinha::query()->with('fruta')->where('id_captacao_lote', $lote->id)->get() as $linha) {
                $frutasPorId[$linha->id_fruta] = $linha->fruta;
            }
        }

        return collect($demandaGalpaoPorFruta)
            ->map(function (array $demanda, int $idFruta) use ($lote, $frutasPorId): array {
                /** @var Fruta|null $fruta */
                $fruta = $frutasPorId[$idFruta] ?? null;
                $casasKg = $fruta?->casasDecimaisKgPorUnidadeMedicao() ?? 3;

                $estoque = Estoque::query()
                    ->where('id_unidade_negocio', $lote->id_unidade_negocio_galpao)
                    ->where('id_fruta', $idFruta)
                    ->where('ativo_unico', 1)
                    ->first();

                $demandaKg = round($demanda['kg'], $casasKg);
                $demandaUm = round($demanda['um'], 2);
                $estoqueKg = $estoque !== null ? round((float) $estoque->qtd_fruta_kg, $casasKg) : 0.0;
                $estoqueUm = $estoque !== null ? round((float) $estoque->qtd_fruta_um, 2) : 0.0;
                $aReceberKg = max(0.0, round($demandaKg - $estoqueKg, $casasKg));
                $aReceberUm = max(0.0, round($demandaUm - $estoqueUm, 2));

                $unidadeMedicao = mb_strtoupper(trim((string) ($fruta?->unidade_medicao ?? '—')), 'UTF-8');

                return [
                    'id_fruta' => $idFruta,
                    'id_cigam' => trim((string) ($fruta?->id_cigam ?? '')),
                    'fruta_nome' => $fruta?->nome ?? '—',
                    'unidade_medicao' => $unidadeMedicao,
                    'demanda_kg' => $this->formatarNumero($demandaKg, $casasKg),
                    'demanda_um' => $this->formatarNumero($demandaUm, 2),
                    'estoque_kg' => $this->formatarNumero($estoqueKg, $casasKg),
                    'estoque_um' => $this->formatarNumero($estoqueUm, 2),
                    'a_receber_kg' => $this->formatarNumero($aReceberKg, $casasKg),
                    'a_receber_um' => $this->formatarNumero($aReceberUm, 2),
                    'demanda_kg_formatado' => $this->formatarBr($demandaKg, $casasKg),
                    'demanda_um_formatado' => $this->formatarBr($demandaUm, 2),
                    'estoque_kg_formatado' => $this->formatarBr($estoqueKg, $casasKg),
                    'estoque_um_formatado' => $this->formatarBr($estoqueUm, 2),
                    'a_receber_kg_formatado' => $this->formatarBr($aReceberKg, $casasKg),
                    'a_receber_um_formatado' => $this->formatarBr($aReceberUm, 2),
                ];
            })
            ->sortBy('fruta_nome')
            ->values();
    }

    /**
     * Quantidade que o HUB precisa possuir: transferência ao galpão + venda direta no HUB.
     *
     * @return Collection<int, array{
     *     id_fruta: int,
     *     fruta_nome: string,
     *     unidade_medicao: string,
     *     necessidade_kg: float,
     *     necessidade_um: float,
     *     necessidade_kg_formatado: string,
     *     necessidade_um_formatado: string,
     * }>
     */
    public function necessidadeEstoqueHub(CaptacaoLote $lote): Collection
    {
        $pedidos = $this->pedidosDoLote($lote);
        $linhasAbastecimento = $this->preview($lote)->keyBy('id_fruta');

        /** @var array<int, array{um: float, kg: float}> $demandaHubPorFruta */
        $demandaHubPorFruta = [];

        foreach ($pedidos as $pedido) {
            if (! $this->pedidoVendeDiretoDoHub($pedido, $lote)) {
                continue;
            }

            foreach ($pedido->itens as $item) {
                $this->acumularDemandaFruta($demandaHubPorFruta, $item->id_fruta, (float) $item->quantidade, (float) $item->fruta->kg_por_unidade_medicao);
            }
        }

        $frutasPorId = $pedidos->flatMap->itens
            ->mapWithKeys(fn ($item) => [(int) $item->id_fruta => $item->fruta])
            ->all();

        $idsFruta = collect($linhasAbastecimento->keys())
            ->merge(array_keys($demandaHubPorFruta))
            ->unique()
            ->map(fn ($id) => (int) $id);

        return $idsFruta
            ->map(function (int $idFruta) use ($linhasAbastecimento, $demandaHubPorFruta, $frutasPorId): ?array {
                $fruta = $frutasPorId[$idFruta] ?? Fruta::query()->find($idFruta);
                $casasKg = $fruta?->casasDecimaisKgPorUnidadeMedicao() ?? 3;

                $linhaAbast = $linhasAbastecimento->get($idFruta);
                $aReceberKg = $linhaAbast !== null ? (float) $linhaAbast['a_receber_kg'] : 0.0;
                $aReceberUm = $linhaAbast !== null ? (float) $linhaAbast['a_receber_um'] : 0.0;

                $demandaHub = $demandaHubPorFruta[$idFruta] ?? ['um' => 0.0, 'kg' => 0.0];
                $necessidadeKg = round($aReceberKg + $demandaHub['kg'], $casasKg);
                $necessidadeUm = round($aReceberUm + $demandaHub['um'], 2);

                if ($necessidadeKg <= 0 && $necessidadeUm <= 0) {
                    return null;
                }

                $unidadeMedicao = mb_strtoupper(trim((string) ($fruta?->unidade_medicao ?? '—')), 'UTF-8');

                return [
                    'id_fruta' => $idFruta,
                    'fruta_nome' => $fruta?->nome ?? '—',
                    'unidade_medicao' => $unidadeMedicao,
                    'necessidade_kg' => $necessidadeKg,
                    'necessidade_um' => $necessidadeUm,
                    'necessidade_kg_formatado' => $this->formatarBr($necessidadeKg, $casasKg),
                    'necessidade_um_formatado' => $this->formatarBr($necessidadeUm, 2),
                ];
            })
            ->filter()
            ->sortBy('fruta_nome')
            ->values();
    }

    /**
     * @param  array<int, array{um: float, kg: float}>  $demandaPorFruta
     */
    private function acumularDemandaFruta(array &$demandaPorFruta, int $idFruta, float $qtdUm, float $kgPorUm): void
    {
        $kg = $qtdUm * $kgPorUm;
        $demandaPorFruta[$idFruta] ??= ['um' => 0.0, 'kg' => 0.0];
        $demandaPorFruta[$idFruta]['um'] += $qtdUm;
        $demandaPorFruta[$idFruta]['kg'] += $kg;
    }

    private function pedidoContaParaAbastecimentoGalpao(Pedido $pedido, CaptacaoLote $lote): bool
    {
        return ! $this->pedidoVendeDiretoDoHub($pedido, $lote);
    }

    private function pedidoVendeDiretoDoHub(Pedido $pedido, CaptacaoLote $lote): bool
    {
        $idHub = $lote->id_unidade_negocio_hub_origem;

        if ($idHub === null || $pedido->id_unidade_negocio_saida_venda === null) {
            return false;
        }

        return (int) $pedido->id_unidade_negocio_saida_venda === (int) $idHub;
    }

    /**
     * @return EloquentCollection<int, Pedido>
     */
    private function pedidosDoLote(CaptacaoLote $lote): EloquentCollection
    {
        $lote->unsetRelation('pedidos');

        return Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->with(['itens.fruta:id,nome,id_cigam,unidade_medicao,kg_por_unidade_medicao'])
            ->orderBy('id')
            ->get();
    }

    private function formatarNumero(float $valor, int $casas): string
    {
        return number_format($valor, $casas, '.', '');
    }

    private function formatarBr(float $valor, int $casas): string
    {
        return number_format($valor, $casas, ',', '.');
    }
}
