<?php

/**
 * Layout EDI NF Cigam — vendas (faturamento → loja).
 * Separado de captacao_cigan_edi.php para evoluir sem afetar transferência.
 *
 * @see docs/decisions/ADR-0126-arquivo-cigan-edi-vendas-faturamento.md
 */
return [
    'transportadora' => env('CAPTACAO_CIGAN_VENDAS_TRANSPORTADORA') ?: '000488',

    'divisao' => env('CAPTACAO_CIGAN_VENDAS_DIVISAO') ?: '10',

    'via_transporte' => 'R',

    'tipo_frete' => '1',

    'entrada_saida' => 'S',

    'especie_estoque' => 'S',

    'comprimento_linha_n' => 688,
    'comprimento_linha_i' => 719,
];
