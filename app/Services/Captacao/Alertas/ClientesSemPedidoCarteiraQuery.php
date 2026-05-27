<?php

namespace App\Services\Captacao\Alertas;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Cliente;
use App\Support\Captacao\DiasSemanaCaptacao;
use Illuminate\Support\Collection;

final class ClientesSemPedidoCarteiraQuery
{
    /**
     * Clientes da carteira sem pedido no lote de captação em andamento do dia (consulta).
     *
     * @return Collection<int, array{
     *     id_cliente: int,
     *     cliente_nome: string,
     *     dias_criacao: list<int>,
     *     dias_envio: list<int>,
     *     dias_criacao_labels: list<string>,
     *     dias_envio_labels: list<string>,
     *     tem_pedido_hoje: bool
     * }>
     */
    public function executar(string $dataReferencia, int $idCaptacaoCarteira): Collection
    {
        $carteira = CaptacaoCarteira::query()->findOrFail($idCaptacaoCarteira);

        $lote = CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_captacao_carteira', $carteira->id)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->first();

        $idsComPedido = $lote !== null
            ? $lote->pedidos()->pluck('id_cliente')->all()
            : [];

        return Cliente::query()
            ->where('id_captacao_carteira', $carteira->id)
            ->with(['captacaoAgenda:id_cliente,dia_semana,tipo'])
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'fantasia'])
            ->map(function (Cliente $cliente) use ($idsComPedido): array {
                $agenda = app(\App\Services\Captacao\ClienteCaptacaoAgendaService::class)
                    ->diasPorCliente($cliente);

                return [
                    'id_cliente' => $cliente->id,
                    'cliente_nome' => (string) ($cliente->fantasia ?: $cliente->razao_social),
                    'dias_criacao' => $agenda['criacao'],
                    'dias_envio' => $agenda['envio'],
                    'dias_criacao_labels' => array_map(
                        fn (int $d) => DiasSemanaCaptacao::label($d),
                        $agenda['criacao'],
                    ),
                    'dias_envio_labels' => array_map(
                        fn (int $d) => DiasSemanaCaptacao::label($d),
                        $agenda['envio'],
                    ),
                    'tem_pedido_hoje' => in_array($cliente->id, $idsComPedido, true),
                ];
            })
            ->filter(fn (array $row): bool => ! $row['tem_pedido_hoje'])
            ->values();
    }
}
