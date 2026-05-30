<?php

namespace App\Services\Captacao\Alertas;

use App\Enums\CaptacaoLoteTipo;
use App\Enums\ClienteCaptacaoAgendaTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use App\Models\User;
use App\Services\Captacao\CaptacaoCarteiraService;
use App\Services\Captacao\ClienteCaptacaoAgendaService;
use App\Support\Captacao\DiasSemanaCaptacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class LojasPendentesCaptacaoQuery
{
    /**
     * @return array{
     *     data_referencia: string,
     *     dia_semana: int,
     *     dia_semana_label: string,
     *     id_captacao_carteira: int|null,
     *     linhas: Collection<int, array{
     *         id_cliente: int,
     *         cliente_nome: string,
     *         id_captacao_carteira: int,
     *         carteira_nome: string,
     *         status: 'pendente'|'iniciou',
     *         captacao_concluida: bool,
     *         dias_criacao_labels: list<string>,
     *         dias_envio_labels: list<string>,
     *     }>,
     *     totais: array{programadas: int, pendentes: int, iniciadas: int}
     * }
     */
    public function executar(string $dataReferencia, User $user, ?int $idCaptacaoCarteira = null): array
    {
        $carteiras = app(CaptacaoCarteiraService::class)
            ->carteirasAcessiveisParaUsuario($user, $idCaptacaoCarteira);

        $carteiraIds = $carteiras->pluck('id')->map(fn ($id) => (int) $id)->all();
        $carteirasPorId = $carteiras->keyBy('id');

        $diaSemana = Carbon::parse($dataReferencia)->dayOfWeek;

        if ($carteiraIds === []) {
            return $this->resultadoVazio($dataReferencia, $diaSemana, $idCaptacaoCarteira);
        }

        $pedidosPorCliente = $this->pedidosPorClienteNaData($dataReferencia, $carteiraIds);

        $agendaService = app(ClienteCaptacaoAgendaService::class);

        $linhas = Cliente::query()
            ->whereIn('id_captacao_carteira', $carteiraIds)
            ->whereHas('captacaoAgenda', function ($query) use ($diaSemana): void {
                $query->where('tipo', ClienteCaptacaoAgendaTipo::CriacaoPedido->value)
                    ->where('dia_semana', $diaSemana);
            })
            ->orderBy('id_captacao_carteira')
            ->orderBy('razao_social')
            ->get(['id', 'razao_social', 'fantasia', 'id_captacao_carteira'])
            ->map(function (Cliente $cliente) use ($carteirasPorId, $pedidosPorCliente, $agendaService): array {
                $carteira = $carteirasPorId->get($cliente->id_captacao_carteira);
                $pedido = $pedidosPorCliente->get($cliente->id);
                $agenda = $agendaService->diasPorCliente($cliente);

                return [
                    'id_cliente' => $cliente->id,
                    'cliente_nome' => (string) ($cliente->fantasia ?: $cliente->razao_social),
                    'id_captacao_carteira' => (int) $cliente->id_captacao_carteira,
                    'carteira_nome' => (string) ($carteira?->nome ?? '—'),
                    'status' => $pedido !== null ? 'iniciou' : 'pendente',
                    'captacao_concluida' => (bool) ($pedido?->captacao_concluida ?? false),
                    'dias_criacao_labels' => array_map(
                        fn (int $d) => DiasSemanaCaptacao::label($d),
                        $agenda['criacao'],
                    ),
                    'dias_envio_labels' => array_map(
                        fn (int $d) => DiasSemanaCaptacao::label($d),
                        $agenda['envio'],
                    ),
                ];
            })
            ->sortBy([
                fn (array $row) => $row['status'] === 'pendente' ? 0 : 1,
                fn (array $row) => $row['carteira_nome'],
                fn (array $row) => $row['cliente_nome'],
            ])
            ->values();

        $pendentes = $linhas->where('status', 'pendente')->count();
        $iniciadas = $linhas->where('status', 'iniciou')->count();

        return [
            'data_referencia' => $dataReferencia,
            'dia_semana' => $diaSemana,
            'dia_semana_label' => DiasSemanaCaptacao::label($diaSemana),
            'id_captacao_carteira' => $idCaptacaoCarteira,
            'linhas' => $linhas,
            'totais' => [
                'programadas' => $linhas->count(),
                'pendentes' => $pendentes,
                'iniciadas' => $iniciadas,
            ],
        ];
    }

    /**
     * @param  list<int>  $carteiraIds
     * @return Collection<int, Pedido>
     */
    private function pedidosPorClienteNaData(string $dataReferencia, array $carteiraIds): Collection
    {
        $loteIds = CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->whereIn('id_captacao_carteira', $carteiraIds)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->pluck('id');

        if ($loteIds->isEmpty()) {
            return collect();
        }

        return Pedido::query()
            ->whereIn('id_captacao_lote', $loteIds)
            ->get(['id', 'id_cliente', 'captacao_concluida'])
            ->keyBy('id_cliente');
    }

    /**
     * @return array{
     *     data_referencia: string,
     *     dia_semana: int,
     *     dia_semana_label: string,
     *     id_captacao_carteira: int|null,
     *     linhas: Collection<int, never>,
     *     totais: array{programadas: int, pendentes: int, iniciadas: int}
     * }
     */
    private function resultadoVazio(string $dataReferencia, int $diaSemana, ?int $idCaptacaoCarteira): array
    {
        return [
            'data_referencia' => $dataReferencia,
            'dia_semana' => $diaSemana,
            'dia_semana_label' => DiasSemanaCaptacao::label($diaSemana),
            'id_captacao_carteira' => $idCaptacaoCarteira,
            'linhas' => collect(),
            'totais' => [
                'programadas' => 0,
                'pendentes' => 0,
                'iniciadas' => 0,
            ],
        ];
    }
}
