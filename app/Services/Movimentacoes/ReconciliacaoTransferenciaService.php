<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use Illuminate\Support\Facades\DB;

/**
 * Recálculo de rateio de frete exclusivo da categoria TRANSFERÊNCIA.
 *
 * Considera apenas pernas de SAÍDA ativas (evita duplicar KG da entrada pendente).
 */
final class ReconciliacaoTransferenciaService
{
    public function recalcularRateioFreteParaTransferencias(int $idFrete): void
    {
        DB::transaction(function () use ($idFrete): void {
            $frete = Frete::query()->whereKey($idFrete)->lockForUpdate()->first();
            if ($frete === null) {
                return;
            }

            $movIds = Movimentacao::query()
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->where('id_frete', $idFrete)
                ->orderBy('id')
                ->pluck('id');

            foreach ($movIds as $movId) {
                Movimentacao::query()->whereKey($movId)->lockForUpdate()->firstOrFail();
            }

            $movs = Movimentacao::query()
                ->whereIn('id', $movIds)
                ->orderBy('data_movimentacao')
                ->orderBy('id')
                ->get();

            if ($movs->isEmpty()) {
                return;
            }

            $totalKg = round((float) $movs->sum(static fn (Movimentacao $m) => (float) $m->qtd_fruta_kg), 2);
            if ($totalKg <= 0) {
                return;
            }

            $valorFreteTotal = (float) $frete->valor;

            foreach ($movs as $saida) {
                $fruta = Fruta::query()->find((int) $saida->id_fruta);
                if ($fruta === null) {
                    continue;
                }

                $qtdKg = (float) $saida->qtd_fruta_kg;
                $qtdUm = (float) $saida->qtd_fruta_um;
                if ($qtdKg <= 0 || $qtdUm <= 0) {
                    continue;
                }

                $valorFreteKg = round($valorFreteTotal / $totalKg, 2);
                $rateio = round($valorFreteKg * $qtdKg, 2);
                $freteUm = round($rateio / $qtdUm, 2);

                $precoOrigemKg = (float) $saida->preco_medio_fruta_kg;
                $precoEntradaKg = round($precoOrigemKg + $valorFreteKg + $this->valorCoEntradaDaPareada($saida) + (float) $this->icmsEntradaDaPareada($saida), 2);
                $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
                $precoEntradaUm = round($precoEntradaKg * $kgPorUm, 2);

                $saida->forceFill([
                    'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
                    'valor_frete_rateio' => number_format($rateio, 2, '.', ''),
                    'valor_frete_um' => number_format($freteUm, 2, '.', ''),
                ])->saveQuietly();

                $entrada = $this->entradaPareadaAtiva($saida);
                if ($entrada === null) {
                    continue;
                }

                $entrada->forceFill([
                    'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
                    'valor_frete_rateio' => number_format($rateio, 2, '.', ''),
                    'valor_frete_um' => number_format($freteUm, 2, '.', ''),
                    'preco_medio_fruta_kg' => number_format($precoEntradaKg, 2, '.', ''),
                    'preco_medio_fruta_um' => number_format($precoEntradaUm, 2, '.', ''),
                ])->saveQuietly();
            }

            $frete->forceFill(['valor_fruta_kg' => number_format($valorFreteTotal / $totalKg, 2, '.', '')])->save();
        });
    }

    /**
     * Perna de entrada ATIVA vinculada à saída (ignora substituída/cancelada).
     */
    private function entradaPareadaAtiva(Movimentacao $saida): ?Movimentacao
    {
        $id = (int) ($saida->pareada_movimentacao_id ?? 0);
        if ($id < 1) {
            return null;
        }

        return Movimentacao::query()
            ->whereKey($id)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->first();
    }

    private function valorCoEntradaDaPareada(Movimentacao $saida): float
    {
        $entrada = $this->entradaPareadaAtiva($saida);

        return $entrada !== null ? (float) $entrada->valor_custo_operacional : 0.0;
    }

    private function icmsEntradaDaPareada(Movimentacao $saida): string
    {
        $entrada = $this->entradaPareadaAtiva($saida);

        return $entrada !== null ? (string) $entrada->icms_convertido_kg : '0.00';
    }
}
