<?php

namespace App\Services\Captacao;

use Exception;

final class NfTransferenciaEstoqueHubInsuficienteException extends Exception
{
    /**
     * @param  list<array{
     *     fruta_nome: string,
     *     unidade_medicao: string,
     *     estoque_um: string,
     *     estoque_kg: string,
     *     falta_um: string,
     *     falta_kg: string,
     * }>  $frutas
     */
    public function __construct(
        public readonly string $hubNome,
        public readonly array $frutas,
    ) {
        parent::__construct('Estoque insuficiente no HUB para envio da NF de transferência.');
    }
}
