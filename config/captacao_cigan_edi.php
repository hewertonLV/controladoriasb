<?php

return [
    /*
    | Tipo de operação Cigan (pos. 20–24 registro N e 372–376 registro I).
    | Deve existir no cadastro Cigan do cliente.
    */
    'tipo_operacao' => env('CAPTACAO_CIGAN_TIPO_OPERACAO', '51101'),

    /** Série NF (pos. 3–7 registro N). */
    'serie' => env('CAPTACAO_CIGAN_SERIE', '1'),

    /** Condição de pagamento (pos. 316–318 registro N). */
    'condicao_pagamento' => env('CAPTACAO_CIGAN_CONDICAO_PAGAMENTO', '001'),

    /** Divisão (pos. 599–600 registro N). */
    'divisao' => env('CAPTACAO_CIGAN_DIVISAO', '10'),

    /** Via transporte: R rodoviária (pos. 44). */
    'via_transporte' => 'R',

    /** Tipo frete: 1 emitente CIF (pos. 266). */
    'tipo_frete' => '1',

    /**
     * Entrada/Saída (pos. 283): E = NF entrada (módulo Compras / transferência para o galpão).
     */
    'entrada_saida' => 'E',

    /** Comprimento fixo das linhas conforme layout Cigam revisão 15/05/2015. */
    'comprimento_linha_n' => 688,
    'comprimento_linha_i' => 719,
];
