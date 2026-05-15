<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;

final class VendaMovimentacaoService extends AbstractMovimentacaoPorCategoriaService
{
    public function categoria(): CategoriaMovimentacaoTipo
    {
        return CategoriaMovimentacaoTipo::Venda;
    }
}
