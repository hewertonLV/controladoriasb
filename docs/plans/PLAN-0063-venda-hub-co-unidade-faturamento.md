# PLAN-0063: Venda com saída no HUB — CO da unidade de faturamento

**ADR:** [ADR-0063](../decisions/ADR-0063-venda-hub-co-unidade-faturamento.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Aplicar CO da unidade de faturamento na margem quando a venda debita estoque do HUB, sem alterar preço médio.

## Pré-requisitos

- ADR-0063 aceita
- ADR-0060/0061 (saída física HUB + realocação) já implementados

## Passos

1. **Domínio** — ajustar `resolverCustoOperacionalHubVenda` em `VendaMovimentacaoService`.
2. **UI** — rótulos do formulário (faturamento, saída física, cliente, frete).
3. **Testes** — venda HUB com CO da loja na margem; produção inalterada.
4. **Verificação** — suite de vendas.

## Critério de conclusão

- Venda loja→HUB aplica CO da loja na margem e PM do HUB/loja inalterado pelo CO.
- Venda produção (saída local) mantém CO do HUB selecionado.
- Testes passam.

## Riscos

- Unidade de faturamento sem histórico CO — usar 0,00 na margem.
