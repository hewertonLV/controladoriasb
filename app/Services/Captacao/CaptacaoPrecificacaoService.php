<?php

namespace App\Services\Captacao;

use App\Models\Estoque;
use App\Models\Fruta;

final class CaptacaoPrecificacaoService
{
    public function custoReferenciaPorKg(int $idUnidadeGalpao, int $idFruta): ?string
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidadeGalpao)
            ->where('id_fruta', $idFruta)
            ->where('ativo_unico', 1)
            ->first();

        if ($estoque === null) {
            return null;
        }

        return $estoque->preco_medio_kg;
    }

    public function margemCalculada(?string $precoVenda, ?string $custoReferencia): ?string
    {
        if ($precoVenda === null || $custoReferencia === null) {
            return null;
        }

        return number_format((float) $precoVenda - (float) $custoReferencia, 4, '.', '');
    }

    public function kgPorUnidadeMedicao(Fruta $fruta): float
    {
        return (float) $fruta->kg_por_unidade_medicao;
    }
}
