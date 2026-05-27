<?php

namespace App\Services\Captacao;

use App\Enums\PedidoOrigem;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CaptacaoHistoricoService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function registrarPedido(Pedido $pedido, string $acao, PedidoOrigem $origem, ?User $user, array $payload = []): void
    {
        DB::table('pedido_historicos')->insert([
            'id_pedido' => $pedido->id,
            'acao' => $acao,
            'origem' => $origem->value,
            'payload' => $payload === [] ? null : json_encode($payload),
            'user_id' => $user?->id,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function registrarItem(PedidoItem $item, string $acao, PedidoOrigem $origem, ?User $user, array $payload = []): void
    {
        DB::table('pedido_item_historicos')->insert([
            'id_pedido_item' => $item->id,
            'acao' => $acao,
            'origem' => $origem->value,
            'payload' => $payload === [] ? null : json_encode($payload),
            'user_id' => $user?->id,
            'created_at' => now(),
        ]);
    }
}
