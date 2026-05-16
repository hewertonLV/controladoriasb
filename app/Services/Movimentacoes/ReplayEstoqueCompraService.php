<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaEstoqueDestinoCompra;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\StatusTransferenciaOperacional;
use App\Models\CategoriaMovimentacao;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Reprocessa a cadeia de {@see MovimentacaoEstoque} e saldos no destino (compras + entradas de transferência já recebidas),
 * usando os valores históricos gravados em cada {@see Movimentacao} (preço médio do lote, frete já rateado na linha, etc.).
 */
final class ReplayEstoqueCompraService implements ReprocessaEstoqueDestinoCompra
{
    private const CATEGORIA_COMPRA_NOME = 'COMPRA';

    /**
     * Recalcula sequencialmente compras ativas e entradas de transferência recebidas conforme no destino,
     * para a unidade de negócio + fruta informadas.
     */
    public function reprocessarEstoqueDestinoUnidadeFruta(int $idUnidadeNegocio, int $idFruta): void
    {
        $fruta = Fruta::query()->findOrFail($idFruta);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        if ($kgPorUm <= 0) {
            throw new InvalidArgumentException('Fruta com kg por unidade inválido.');
        }

        $empresaDestinoId = $this->empresaIdDaUnidade($idUnidadeNegocio);

        $categoriaCompraId = CategoriaMovimentacao::idPorNome(self::CATEGORIA_COMPRA_NOME);

        $compras = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', $categoriaCompraId)
            ->where('id_empresa_destino', $empresaDestinoId)
            ->where('id_fruta', $idFruta)
            ->ordenarLinhaDoTempo()
            ->get();

        $entradasTransferencia = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('id_empresa_destino', $empresaDestinoId)
            ->where('id_fruta', $idFruta)
            ->where('status_transferencia', StatusTransferenciaOperacional::RECEBIDA_CONFORME->value)
            ->ordenarLinhaDoTempo()
            ->get();

        $eventos = $this->mesclarEventosDestino($compras, $entradasTransferencia);

        $ids = $eventos->map(static fn (array $e): int => (int) $e['movimentacao']->id)->sort()->values()->all();
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

        $baseline->forceFill([
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => false,
        ])->save();

        $prevMeId = (int) $baseline->id;

        $runUm = 0.0;
        $runKg = 0.0;
        $V = 0.0;

        $ultimaMeId = $prevMeId;

        if ($eventos->isEmpty()) {
            MovimentacaoEstoque::query()->whereKey($ultimaMeId)->update(['status_ultima_posicao' => true]);
            $estoque->forceFill([
                'qtd_fruta_kg' => '0.00',
                'qtd_fruta_um' => '0.00',
                'preco_medio_kg' => '0.00',
                'preco_medio_um' => '0.00',
                'valor_total_acumulado' => '0.00',
            ])->save();

            return;
        }

        foreach ($eventos as $evento) {
            /** @var Movimentacao $m */
            $m = $evento['movimentacao'];
            $tipo = $evento['tipo'];

            if ($tipo === 'compra') {
                $qUm = (float) $m->qtd_fruta_um;
                $qKg = (float) $m->qtd_fruta_kg;
            } else {
                $qUm = (float) $m->qtd_recebida_um;
                $qKg = (float) $m->qtd_recebida_kg;
            }

            if ($qUm <= 0 || $qKg <= 0) {
                throw new InvalidArgumentException('Quantidade inválida durante replay de destino.');
            }

            $precoLoteKg = (float) $m->preco_medio_fruta_kg;

            $runUm = round($runUm + $qUm, 2);
            $runKg = round($runKg + $qKg, 2);
            $V = round($V + ($precoLoteKg * $qKg), 2);

            $precoConsolidadoKg = $runKg > 0 ? round($V / $runKg, 2) : 0.0;
            $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);
            $valorTotalSnapshot = round($runKg * $precoConsolidadoKg, 2);

            $me = MovimentacaoEstoque::query()->firstOrNew([
                'movimentacao_id' => $m->id,
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

            $m->forceFill([
                'id_movimentacao_estoque_old' => $prevMeId,
                'id_movimentacao_estoque_new' => $me->id,
                'saldo_estoque_fruta_kg' => number_format($runKg, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($runUm, 2, '.', ''),
            ])->saveQuietly();

            $prevMeId = (int) $me->id;
            $ultimaMeId = $prevMeId;
        }

        MovimentacaoEstoque::query()->whereKey($ultimaMeId)->update(['status_ultima_posicao' => true]);

        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($runKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($runUm, 2, '.', ''),
            'preco_medio_kg' => number_format($runKg > 0 ? round($V / $runKg, 2) : 0, 2, '.', ''),
            'preco_medio_um' => number_format($runKg > 0 ? round(($V / $runKg) * $kgPorUm, 2) : 0, 2, '.', ''),
            'valor_total_acumulado' => number_format($V, 2, '.', ''),
        ])->save();
    }

    /**
     * @param  Collection<int, Movimentacao>  $compras
     * @param  Collection<int, Movimentacao>  $entradasTransferencia
     * @return Collection<int, array{tipo: string, movimentacao: Movimentacao}>
     */
    private function mesclarEventosDestino(Collection $compras, Collection $entradasTransferencia): Collection
    {
        $eventos = collect();

        foreach ($compras as $m) {
            $eventos->push(['tipo' => 'compra', 'movimentacao' => $m]);
        }
        foreach ($entradasTransferencia as $m) {
            $eventos->push(['tipo' => 'entrada_transferencia', 'movimentacao' => $m]);
        }

        return $eventos->sortBy(static function (array $e): array {
            $m = $e['movimentacao'];

            return [
                (string) $m->data_movimentacao,
                (int) ($m->movimentacao_origem_id ?? $m->id),
                (int) $m->versao,
                (int) $m->id,
            ];
        })->values();
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
