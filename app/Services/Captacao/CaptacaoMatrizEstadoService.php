<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;

final class CaptacaoMatrizEstadoService
{
    public function __construct(
        private readonly ClienteFrutaVinculoService $vinculos,
    ) {}
    /**
     * @return array{
     *     lote_id: int,
     *     version: int,
     *     updated_at: string,
     *     clientes: list<array{id: int, nome: string}>,
     *     frutas: list<array{id: int, nome: string}>,
     *     celulas: array<string, array{quantidade: string, preco_venda: string|null, version: int}>,
     *     frutas_por_cliente: array<int, list<int>>,
     *     layout_hash: string,
     * }
     */
    public function snapshot(CaptacaoLote $lote): array
    {
        $lote->load(['pedidos.itens', 'pedidos.cliente:id,razao_social,fantasia']);

        $matriz = $this->vinculos->dadosMatriz($lote);
        $clientes = $matriz['clientes'];
        $frutas = $matriz['frutas'];

        $celulas = [];
        $versionSum = 0;

        foreach ($lote->pedidos as $pedido) {
            foreach ($pedido->itens as $item) {
                $key = $pedido->id_cliente.'_'.$item->id_fruta;
                $celulas[$key] = [
                    'quantidade' => (string) $item->quantidade,
                    'preco_venda' => $item->preco_venda !== null ? (string) $item->preco_venda : null,
                    'version' => (int) $item->version,
                ];
                $versionSum += (int) $item->version;
            }
        }

        return [
            'lote_id' => $lote->id,
            'version' => $versionSum + (int) $lote->updated_at?->timestamp,
            'updated_at' => $lote->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'layout_hash' => $matriz['layout_hash'],
            'clientes' => $clientes->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->fantasia ?: $c->razao_social,
            ])->values()->all(),
            'frutas' => $frutas->map(fn ($f) => [
                'id' => $f->id,
                'nome' => $f->nome,
            ])->values()->all(),
            'frutas_por_cliente' => $matriz['frutasPorCliente'],
            'celulas' => $celulas,
        ];
    }
}
