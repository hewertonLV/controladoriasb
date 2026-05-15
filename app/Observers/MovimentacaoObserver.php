<?php

namespace App\Observers;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\MovimentacaoService;

class MovimentacaoObserver
{
    public function __construct(
        private readonly MovimentacaoService $movimentacoes,
    ) {}

    public function saving(Movimentacao $movimentacao): void
    {
        if ((int) $movimentacao->categoria_movimentacao_id === CategoriaMovimentacaoTipo::Compra->value) {
            return;
        }

        $this->movimentacoes->sincronizarQuantidadeUnidadeMedida($movimentacao);
    }

    public function created(Movimentacao $movimentacao): void
    {
        $this->movimentacoes->aposCriar($movimentacao);
    }

    public function updated(Movimentacao $movimentacao): void
    {
        $this->movimentacoes->aposAtualizar($movimentacao);
    }

    public function deleting(Movimentacao $movimentacao): void
    {
        $this->movimentacoes->antesDeApagar($movimentacao);
    }
}
