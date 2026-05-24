<?php

namespace App\Services\Captacao\Alertas;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\Pedido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class FrutasHabituaisAusentesQuery
{
    /**
     * @return Collection<int, array{id_cliente: int, cliente_nome: string, id_fruta: int, fruta_nome: string}>
     */
    public function executar(
        string $dataReferencia,
        int $idUnidadeFaturamento,
        ?int $idUnidadeGalpao = null,
        int $limiarSemanas = 2,
        int $janelaSemanas = 4,
    ): Collection {
        $data = Carbon::parse($dataReferencia);
        $weekday = $data->dayOfWeek;

        $datasHistorico = collect(range(1, $janelaSemanas))
            ->map(fn (int $i) => $data->copy()->subWeeks($i)->toDateString());

        $habitual = DB::table('pedido_itens')
            ->join('pedidos', 'pedidos.id', '=', 'pedido_itens.id_pedido')
            ->join('captacao_lotes', 'captacao_lotes.id', '=', 'pedidos.id_captacao_lote')
            ->join('clientes', 'clientes.id', '=', 'pedidos.id_cliente')
            ->join('frutas', 'frutas.id', '=', 'pedido_itens.id_fruta')
            ->where('captacao_lotes.id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->when($idUnidadeGalpao !== null, fn ($q) => $q->where('captacao_lotes.id_unidade_negocio_galpao', $idUnidadeGalpao))
            ->whereIn('captacao_lotes.data_referencia', $datasHistorico->all())
            ->whereRaw('DAYOFWEEK(captacao_lotes.data_referencia) = ?', [$weekday + 1])
            ->groupBy('pedidos.id_cliente', 'clientes.razao_social', 'clientes.fantasia', 'pedido_itens.id_fruta', 'frutas.nome')
            ->selectRaw('pedidos.id_cliente, COALESCE(clientes.fantasia, clientes.razao_social) as cliente_nome, pedido_itens.id_fruta, frutas.nome as fruta_nome, COUNT(DISTINCT captacao_lotes.data_referencia) as ocorrencias')
            ->having('ocorrencias', '>=', $limiarSemanas)
            ->get();

        $pedidosHoje = Pedido::query()
            ->with('itens:id_pedido,id_fruta')
            ->whereHas('lote', function ($q) use ($dataReferencia, $idUnidadeFaturamento, $idUnidadeGalpao): void {
                $q->where('data_referencia', $dataReferencia)
                    ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
                    ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento);

                if ($idUnidadeGalpao !== null) {
                    $q->where('id_unidade_negocio_galpao', $idUnidadeGalpao);
                }
            })
            ->get();

        $frutasHojePorCliente = $pedidosHoje->mapWithKeys(fn (Pedido $p) => [
            $p->id_cliente => $p->itens->pluck('id_fruta')->all(),
        ]);

        $clientesComPedidoHoje = $pedidosHoje->pluck('id_cliente');

        return $habitual
            ->filter(fn ($row) => $clientesComPedidoHoje->contains($row->id_cliente))
            ->reject(function ($row) use ($frutasHojePorCliente): bool {
                $frutas = $frutasHojePorCliente[(int) $row->id_cliente] ?? [];

                return in_array((int) $row->id_fruta, $frutas, true);
            })
            ->map(fn ($row) => [
                'id_cliente' => (int) $row->id_cliente,
                'cliente_nome' => (string) $row->cliente_nome,
                'id_fruta' => (int) $row->id_fruta,
                'fruta_nome' => (string) $row->fruta_nome,
            ])
            ->values();
    }
}
