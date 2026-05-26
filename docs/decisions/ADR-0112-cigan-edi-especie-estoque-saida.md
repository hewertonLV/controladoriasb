# ADR-0112: Espécie estoque «S» no EDI Cigan

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Importação TXT transferência ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

O Cigan exibe o campo **Espécie estoque** na importação do EDI NF. O valor deve ser **S** (saída), alinhado a `entrada_saida` = S da transferência da carteira.

## Decisão

- Registro **N**, pos. **608**: `S`; pos. **605–607** = centro de armazenagem do HUB — [ADR-0116](ADR-0116-cigan-edi-centro-armazenagem-hub.md).
- Registro **I**, pos. **679**: `S`; pos. **659–661** = mesmo centro; **662–678** em branco após UN 656–658.
- Registro **I**, pos. **39** = espaço (separador entre quantidade e peças).
- Config `captacao_cigan_edi.especie_estoque` (default `S`).
- Unidade de negócio do HUB permanece nas pos. 602–604 (N) e 656–658 (I); sequência item (681–685) em branco.

## Alternativas consideradas

- Deixar em branco — rejeitado: Cigan não interpreta corretamente a espécie.
- Valor variável por operação — rejeitado: transferência da carteira é sempre saída.

## Consequências

- [PLAN-0112](../plans/PLAN-0112-cigan-edi-especie-estoque-saida.md).
