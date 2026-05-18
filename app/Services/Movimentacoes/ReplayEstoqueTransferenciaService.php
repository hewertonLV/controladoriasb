<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasTransferenciaOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use InvalidArgumentException;

/**
 * Reprocessa exclusivamente pernas de SAÍDA de transferências ativas na unidade de origem,
 * reconstruindo {@see MovimentacaoEstoque} e os saldos registrados nas movimentações.
 */
final class ReplayEstoqueTransferenciaService implements ReprocessaSaidasTransferenciaOrigem
{
    /**
     * Recalcula a cadeia de saídas de transferência (origem) para a unidade + fruta.
     */
    public function reprocessarSaidasTransferenciaNaUnidadeOrigem(int $idUnidadeNegocio, int $idFruta): void
    {
        $fruta = Fruta::query()->findOrFail($idFruta);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        if ($kgPorUm <= 0) {
            throw new InvalidArgumentException('Fruta com kg por unidade inválido.');
        }

        $empresaOrigemId = $this->empresaIdDaUnidade($idUnidadeNegocio);

        $saidas = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('id_empresa_origem', $empresaOrigemId)
            ->where('id_fruta', $idFruta)
            ->orderBy('data_movimentacao')
            ->orderBy('movimentacao_origem_id')
            ->orderBy('versao')
            ->orderBy('id')
            ->get();

        $ids = $saidas->map(static fn (Movimentacao $m): int => (int) $m->id)->sort()->values()->all();
        if ($ids !== []) {
            Movimentacao::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->firstOrFail();

        MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('id_fruta', $idFruta)
            ->update(['status_ultima_posicao' => false]);

        $baseline = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('id_fruta', $idFruta)
            ->whereNull('movimentacao_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($baseline === null) {
            $baseline = MovimentacaoEstoque::query()->create([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $idUnidadeNegocio,
                'id_fruta' => $idFruta,
                'movimentacao_id' => null,
                'qtd_fruta_kg' => '0.00',
                'qtd_fruta_um' => '0.00',
                'preco_medio_kg' => '0.00',
                'preco_medio_um' => '0.00',
                'valor_total_fruta' => '0.00',
                'status_ultima_posicao' => false,
            ]);
        }

        $runUm = (float) $estoque->qtd_fruta_um;
        $runKg = (float) $estoque->qtd_fruta_kg;
        $V = (float) $estoque->valor_total_acumulado;

        foreach ($saidas->reverse() as $saida) {
            $qUm = (float) $saida->qtd_fruta_um;
            $qKg = (float) $saida->qtd_fruta_kg;
            $precoMedioKg = $runKg > 1e-12 ? round($V / $runKg, 2) : 0.0;
            $runUm = round($runUm + $qUm, 2);
            $runKg = round($runKg + $qKg, 2);
            $V = round($V + ($precoMedioKg * $qKg), 2);
        }

        $baseline->forceFill([
            'qtd_fruta_kg' => number_format($runKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($runUm, 2, '.', ''),
            'preco_medio_kg' => number_format($runKg > 0 ? round($V / $runKg, 2) : 0, 2, '.', ''),
            'preco_medio_um' => number_format($runKg > 0 ? round(($V / $runKg) * $kgPorUm, 2) : 0, 2, '.', ''),
            'valor_total_fruta' => number_format($runKg > 0 ? round($runKg * ($V / $runKg), 2) : 0, 2, '.', ''),
            'status_ultima_posicao' => false,
        ])->save();

        $prevMeId = (int) $baseline->id;
        $ultimaMeId = $prevMeId;

        if ($saidas->isEmpty()) {
            MovimentacaoEstoque::query()->whereKey($ultimaMeId)->update(['status_ultima_posicao' => true]);

            return;
        }

        foreach ($saidas as $saida) {
            $qUm = (float) $saida->qtd_fruta_um;
            $qKg = (float) $saida->qtd_fruta_kg;
            $precoMedioKg = $runKg > 1e-12 ? round($V / $runKg, 2) : 0.0;

            if ($runUm + 1e-6 < $qUm || $runKg + 1e-6 < $qKg) {
                throw new InvalidArgumentException('Saldo insuficiente na origem durante replay de transferências.');
            }

            $runUm = round($runUm - $qUm, 2);
            $runKg = round($runKg - $qKg, 2);
            $V = round($V - ($precoMedioKg * $qKg), 2);

            $precoConsolidadoKg = $runKg > 0 ? round($V / $runKg, 2) : 0.0;
            $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);
            $valorTotalSnapshot = round($runKg * $precoConsolidadoKg, 2);

            $me = MovimentacaoEstoque::query()->firstOrNew([
                'movimentacao_id' => $saida->id,
            ]);

            $me->forceFill([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $idUnidadeNegocio,
                'id_fruta' => $idFruta,
                'qtd_fruta_kg' => number_format($runKg, 2, '.', ''),
                'qtd_fruta_um' => number_format($runUm, 2, '.', ''),
                'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
                'valor_total_fruta' => number_format($valorTotalSnapshot, 2, '.', ''),
                'status_ultima_posicao' => false,
            ])->save();

            $saida->forceFill([
                'id_movimentacao_estoque_old' => $prevMeId,
                'id_movimentacao_estoque_new' => $me->id,
                'saldo_estoque_fruta_kg' => number_format($runKg, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($runUm, 2, '.', ''),
                'versao_replay' => (int) ($saida->versao_replay ?? 1) + 1,
            ])->saveQuietly();

            $prevMeId = (int) $me->id;
            $ultimaMeId = $prevMeId;
        }

        MovimentacaoEstoque::query()->whereKey($ultimaMeId)->update(['status_ultima_posicao' => true]);

        $precoMedioEstoqueKg = $runKg > 0 ? round($V / $runKg, 2) : 0.0;
        $precoMedioEstoqueUm = round($precoMedioEstoqueKg * $kgPorUm, 2);

        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($runKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($runUm, 2, '.', ''),
            'preco_medio_kg' => number_format($precoMedioEstoqueKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoMedioEstoqueUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($V, 2, '.', ''),
        ])->save();
    }

    private function empresaIdDaUnidade(int $idUnidadeNegocio): int
    {
        $empresa = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->where('entidade_id', $idUnidadeNegocio)
            ->firstOrFail();

        return (int) $empresa->id;
    }
}
