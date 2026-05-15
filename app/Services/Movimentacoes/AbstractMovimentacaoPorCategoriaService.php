<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use InvalidArgumentException;

/**
 * Base para serviços por categoria (compra, venda, …): valida referências e exige categoria coerente.
 */
abstract class AbstractMovimentacaoPorCategoriaService
{
    public function __construct(
        protected readonly MovimentacaoService $movimentacoes,
    ) {}

    abstract public function categoria(): CategoriaMovimentacaoTipo;

    /**
     * @param  array<string, mixed>  $dados
     */
    public function assertPodeRegistrar(array $dados): void
    {
        $this->movimentacoes->validarReferencias($dados);

        $id = (int) ($dados['categoria_movimentacao_id'] ?? 0);
        if ($id !== $this->categoria()->value) {
            throw new InvalidArgumentException(
                sprintf(
                    'Esta operação exige a categoria «%s».',
                    $this->categoria()->nomeLegivel(),
                ),
            );
        }
    }
}
