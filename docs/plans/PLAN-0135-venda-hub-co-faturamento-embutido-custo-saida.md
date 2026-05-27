# PLAN-0135: Venda HUB — CO faturamento no custo de saída

**ADR:** [ADR-0135](../decisions/ADR-0135-venda-hub-co-faturamento-embutido-custo-saida.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Embutir CO da unidade de faturamento no `valor_custo_saida` das vendas com saída física HUB, sem afetar PM do saldo remanescente.

## Passos

1. Helper + refatorar `VendaMovimentacaoService` (criar/atualizar venda, frete zero).
2. Ajustar `FreteRateioMovimentacaoService::atualizarVenda`.
3. UI `vendas/show` — observação e detalhe do CO embutido.
4. Testes `VendaMovimentacaoTest` e captação com saída HUB.

## Critério de conclusão

Venda com saída HUB: `valor_custo_saida` inclui CO×kg; margem não duplica CO; estoque HUB mantém PM; observação preenchida.

## Riscos

- Duplicar CO na margem ao recalcular frete — mitigado com helper compartilhado.
