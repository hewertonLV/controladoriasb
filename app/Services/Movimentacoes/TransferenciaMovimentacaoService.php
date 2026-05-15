<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;

final class TransferenciaMovimentacaoService extends AbstractMovimentacaoPorCategoriaService
{
    public function categoria(): CategoriaMovimentacaoTipo
    {
        return CategoriaMovimentacaoTipo::Transferencia;
    }
}
