<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;

final class DevolucaoMovimentacaoService extends AbstractMovimentacaoPorCategoriaService
{
    public function categoria(): CategoriaMovimentacaoTipo
    {
        return CategoriaMovimentacaoTipo::Devolucao;
    }
}
