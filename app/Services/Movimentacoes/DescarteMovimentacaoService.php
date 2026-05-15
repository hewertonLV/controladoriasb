<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;

final class DescarteMovimentacaoService extends AbstractMovimentacaoPorCategoriaService
{
    public function categoria(): CategoriaMovimentacaoTipo
    {
        return CategoriaMovimentacaoTipo::Descarte;
    }
}
