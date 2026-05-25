<?php

namespace App\Services\Captacao;

use App\Enums\ClienteCaptacaoAgendaTipo;
use App\Models\Captacao\ClienteCaptacaoAgenda;
use App\Models\Cliente;

final class ClienteCaptacaoAgendaService
{
    /**
     * @param  list<int|string>  $diasCriacao
     * @param  list<int|string>  $diasEnvio
     */
    public function sincronizar(Cliente $cliente, array $diasCriacao, array $diasEnvio): void
    {
        ClienteCaptacaoAgenda::query()
            ->where('id_cliente', $cliente->id)
            ->delete();

        foreach ($this->normalizarDias($diasCriacao) as $dia) {
            ClienteCaptacaoAgenda::query()->create([
                'id_cliente' => $cliente->id,
                'dia_semana' => $dia,
                'tipo' => ClienteCaptacaoAgendaTipo::CriacaoPedido,
            ]);
        }

        foreach ($this->normalizarDias($diasEnvio) as $dia) {
            ClienteCaptacaoAgenda::query()->create([
                'id_cliente' => $cliente->id,
                'dia_semana' => $dia,
                'tipo' => ClienteCaptacaoAgendaTipo::EnvioPedido,
            ]);
        }
    }

    /**
     * @return array{criacao: list<int>, envio: list<int>}
     */
    public function diasPorCliente(Cliente $cliente): array
    {
        $linhas = ClienteCaptacaoAgenda::query()
            ->where('id_cliente', $cliente->id)
            ->get(['dia_semana', 'tipo']);

        $criacao = [];
        $envio = [];

        foreach ($linhas as $linha) {
            if ($linha->tipo === ClienteCaptacaoAgendaTipo::CriacaoPedido) {
                $criacao[] = (int) $linha->dia_semana;
            } else {
                $envio[] = (int) $linha->dia_semana;
            }
        }

        sort($criacao);
        sort($envio);

        return ['criacao' => $criacao, 'envio' => $envio];
    }

    /**
     * @param  list<int|string>  $dias
     * @return list<int>
     */
    private function normalizarDias(array $dias): array
    {
        return collect($dias)
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d) => $d >= 0 && $d <= 6)
            ->unique()
            ->values()
            ->all();
    }
}
