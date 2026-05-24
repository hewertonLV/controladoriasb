<?php

namespace App\Services\Captacao\Alertas;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LojasSemPedidoDiaSemanaQuery
{
    /**
     * @return Collection<int, array{id_cliente: int, cliente_nome: string, ocorrencias: int}>
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

        $habitual = DB::table('pedidos')
            ->join('captacao_lotes', 'captacao_lotes.id', '=', 'pedidos.id_captacao_lote')
            ->join('clientes', 'clientes.id', '=', 'pedidos.id_cliente')
            ->where('captacao_lotes.id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->when($idUnidadeGalpao !== null, fn ($q) => $q->where('captacao_lotes.id_unidade_negocio_galpao', $idUnidadeGalpao))
            ->whereIn('captacao_lotes.data_referencia', $datasHistorico->all())
            ->whereRaw('DAYOFWEEK(captacao_lotes.data_referencia) = ?', [$weekday + 1])
            ->groupBy('pedidos.id_cliente', 'clientes.razao_social', 'clientes.fantasia')
            ->selectRaw('pedidos.id_cliente, COALESCE(clientes.fantasia, clientes.razao_social) as cliente_nome, COUNT(DISTINCT captacao_lotes.data_referencia) as ocorrencias')
            ->having('ocorrencias', '>=', $limiarSemanas)
            ->get();

        $captadosHoje = Pedido::query()
            ->whereHas('lote', function ($q) use ($dataReferencia, $idUnidadeFaturamento, $idUnidadeGalpao): void {
                $q->where('data_referencia', $dataReferencia)
                    ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
                    ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento);

                if ($idUnidadeGalpao !== null) {
                    $q->where('id_unidade_negocio_galpao', $idUnidadeGalpao);
                }
            })
            ->pluck('id_cliente');

        return $habitual
            ->reject(fn ($row) => $captadosHoje->contains($row->id_cliente))
            ->map(fn ($row) => [
                'id_cliente' => (int) $row->id_cliente,
                'cliente_nome' => (string) $row->cliente_nome,
                'ocorrencias' => (int) $row->ocorrencias,
            ])
            ->values();
    }
}
