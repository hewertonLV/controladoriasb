<?php

namespace App\Enums;

use App\Models\CategoriaMovimentacao;

/**
 * Espelha os IDs fixos de {@see CategoriaMovimentacao} (seed/banco).
 */
enum CategoriaMovimentacaoTipo: int
{
    case Compra = CategoriaMovimentacao::ID_COMPRA;
    case Transferencia = CategoriaMovimentacao::ID_TRANSFERENCIA;
    case Venda = CategoriaMovimentacao::ID_VENDA;
    case Doacao = CategoriaMovimentacao::ID_DOACAO;
    case Descarte = CategoriaMovimentacao::ID_DESCARTE;
    case Devolucao = CategoriaMovimentacao::ID_DEVOLUCAO;
    case ConversaoEmbalagem = CategoriaMovimentacao::ID_CONVERSAO_EMBALAGEM;

    public function nomeLegivel(): string
    {
        return match ($this) {
            self::Compra => 'Compra',
            self::Transferencia => 'Transferência',
            self::Venda => 'Venda',
            self::Doacao => 'Doação',
            self::Descarte => 'Descarte',
            self::Devolucao => 'Devolução',
            self::ConversaoEmbalagem => 'Conversão de embalagem',
        };
    }
}
