<?php

return [
    /** Série NF (pos. 3–7 registro N; 3 dígitos com 2 espaços à esquerda no TXT). */
    'serie' => env('CAPTACAO_CIGAN_SERIE') ?: '1',

    /** Transportadora (pos. 132–137 registro N) — código fixo Cigam. */
    'transportadora' => env('CAPTACAO_CIGAN_TRANSPORTADORA') ?: '000488',

    /** Divisão (pos. 599–600 registro N). */
    'divisao' => env('CAPTACAO_CIGAN_DIVISAO') ?: '10',

    /** Via transporte: R rodoviária (pos. 44). */
    'via_transporte' => 'R',

    /** Tipo frete: 1 emitente CIF (pos. 266). */
    'tipo_frete' => '1',

    /**
     * Tipo de operação (pos. 20–24 no N; 372–376 no I): transferência HUB (CFOP 5152).
     */
    'tipo_operacao' => env('CAPTACAO_CIGAN_TIPO_OPERACAO') ?: '5152A',

    /**
     * Entrada/Saída/Nula (pos. 283): S = saída (Faturamento), conforme operação da carteira.
     */
    'entrada_saida' => 'S',

    /** Espécie estoque (pos. 608 no N; 679 no I): S = saída. */
    'especie_estoque' => 'S',

    /** Comprimento fixo das linhas conforme layout Cigam revisão 15/05/2015. */
    'comprimento_linha_n' => 688,
    'comprimento_linha_i' => 719,
];
