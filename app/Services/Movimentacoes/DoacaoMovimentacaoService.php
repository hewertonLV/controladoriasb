<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;

final class DoacaoMovimentacaoService extends AbstractMovimentacaoPorCategoriaService
{
    public function categoria(): CategoriaMovimentacaoTipo
    {
        return CategoriaMovimentacaoTipo::Doacao;
    }
}
