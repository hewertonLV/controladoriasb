<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRomaneioManualLinha;
use App\Models\Estoque;
use App\Models\Fruta;
use Illuminate\Support\Collection;

final class RomaneioAbastecimentoService
{
    /**
     * @return Collection<int, array{
     *     id_fruta: int,
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
        $lote->load(['pedidos.itens.fruta:id,nome,unidade_medicao,kg_por_unidade_medicao']);

        /** @var array<int, array{um: float, kg: float}> $demandaPorFruta */
        $demandaPorFruta = [];

        foreach ($lote->pedidos as $pedido) {
            foreach ($pedido->itens as $item) {
                $qtdUm = (float) $item->quantidade;
                $kg = $qtdUm * (float) $item->fruta->kg_por_unidade_medicao;
                $demandaPorFruta[$item->id_fruta] ??= ['um' => 0.0, 'kg' => 0.0];
                $demandaPorFruta[$item->id_fruta]['um'] += $qtdUm;
                $demandaPorFruta[$item->id_fruta]['kg'] += $kg;
            }
        }

        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            $linhasManuais = CaptacaoRomaneioManualLinha::query()
                ->with('fruta:id,nome,unidade_medicao,kg_por_unidade_medicao')
                ->where('id_captacao_lote', $lote->id)
                ->get();

            foreach ($linhasManuais as $linha) {
                $qtdUm = (float) $linha->quantidade;
                $kg = $qtdUm * (float) $linha->fruta->kg_por_unidade_medicao;
                $demandaPorFruta[$linha->id_fruta] ??= ['um' => 0.0, 'kg' => 0.0];
                $demandaPorFruta[$linha->id_fruta]['um'] += $qtdUm;
                $demandaPorFruta[$linha->id_fruta]['kg'] += $kg;
            }
        }

        $frutasPorId = $lote->pedidos->flatMap->itens
            ->mapWithKeys(fn ($item) => [$item->id_fruta => $item->fruta])
            ->all();

        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            foreach (CaptacaoRomaneioManualLinha::query()->with('fruta')->where('id_captacao_lote', $lote->id)->get() as $linha) {
                $frutasPorId[$linha->id_fruta] = $linha->fruta;
            }
        }

        return collect($demandaPorFruta)
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

    private function formatarNumero(float $valor, int $casas): string
    {
        return number_format($valor, $casas, '.', '');
    }

    private function formatarBr(float $valor, int $casas): string
    {
        return number_format($valor, $casas, ',', '.');
    }
}
