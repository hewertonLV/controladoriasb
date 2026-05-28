<?php

namespace App\Support\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;

final class SaidaEstoqueFisicoCaptacaoService
{
    public function idGalpaoLote(CaptacaoLote $lote): int
    {
        return (int) $lote->id_unidade_negocio_galpao;
    }

    public function idHubLote(CaptacaoLote $lote): ?int
    {
        return $lote->id_unidade_negocio_hub_origem !== null
            ? (int) $lote->id_unidade_negocio_hub_origem
            : null;
    }

    public function idSaidaPadraoParaCliente(Cliente $cliente, CaptacaoLote $lote): int
    {
        $preferido = $cliente->id_unidade_negocio_saida_fisico_padrao !== null
            ? (int) $cliente->id_unidade_negocio_saida_fisico_padrao
            : null;

        $permitidas = $this->idsUnidadesPermitidas($lote);

        if ($preferido !== null && in_array($preferido, $permitidas, true)) {
            return $preferido;
        }

        if ($preferido !== null) {
            $unidade = UnidadeNegocio::query()->find($preferido);

            if ($unidade?->is_hub && $this->idHubLote($lote) !== null) {
                return $this->idHubLote($lote);
            }

            if ($unidade?->is_galpao_operacional) {
                return $this->idGalpaoLote($lote);
            }
        }

        return $this->idGalpaoLote($lote);
    }

    public function idSaidaEfetiva(Pedido $pedido, CaptacaoLote $lote, ?Cliente $cliente = null): int
    {
        if ($pedido->id_unidade_negocio_saida_venda !== null) {
            return (int) $pedido->id_unidade_negocio_saida_venda;
        }

        $cliente ??= $pedido->relationLoaded('cliente') ? $pedido->cliente : null;

        if ($cliente !== null) {
            return $this->idSaidaPadraoParaCliente($cliente, $lote);
        }

        return $this->idGalpaoLote($lote);
    }

    /**
     * @return list<int>
     */
    public function idsUnidadesPermitidas(CaptacaoLote $lote): array
    {
        $ids = [$this->idGalpaoLote($lote)];
        $hub = $this->idHubLote($lote);
        if ($hub !== null) {
            $ids[] = $hub;
        }

        return $ids;
    }

    public function unidadePermitida(CaptacaoLote $lote, int $idUnidade): bool
    {
        return in_array($idUnidade, $this->idsUnidadesPermitidas($lote), true);
    }
}
