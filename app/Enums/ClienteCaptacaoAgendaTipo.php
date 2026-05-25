<?php

namespace App\Enums;

enum ClienteCaptacaoAgendaTipo: string
{
    case CriacaoPedido = 'CRIACAO_PEDIDO';
    case EnvioPedido = 'ENVIO_PEDIDO';

    public function label(): string
    {
        return match ($this) {
            self::CriacaoPedido => 'Criação do pedido',
            self::EnvioPedido => 'Envio do pedido',
        };
    }
}
