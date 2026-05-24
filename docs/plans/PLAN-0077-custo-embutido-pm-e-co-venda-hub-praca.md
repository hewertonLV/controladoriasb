# PLAN-0077: PM como custo único; CO venda HUB por praça

**ADR:** [ADR-0077](../decisions/ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Custo na captação = só PM do estoque; CO na margem da venda apenas saída HUB com UN da praça do cliente.

## Passos

1. Atualizar `ResolverCustoReferenciaCaptacaoService` — só PM; sem fallback vendas.
2. Resolver UN de CO por `cliente.id_praca` na venda HUB; ajustar `VendaMovimentacaoService`.
3. Atualizar testes 0063/0073 e documentação ADR-0063 (nota de refinamento).
4. UI app/matriza: mensagem “custo = preço médio do estoque (já inclui frete/impostos lançados)”.

## Critério de conclusão

Captação sem estoque não inventa custo; venda HUB aplica CO da praça na margem.
