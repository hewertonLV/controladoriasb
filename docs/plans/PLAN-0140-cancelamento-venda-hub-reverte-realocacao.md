# PLAN-0140: Cancelamento de venda HUB reverte realocação automática

**ADR:** [ADR-0140](../decisions/ADR-0140-cancelamento-venda-hub-reverte-realocacao.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Restaurar estoque hub/loja e transferências após cancelamento de venda com saída física HUB que havia disparado realocação automática.

## Pré-requisitos

- ADR-0061 (realocação na venda HUB) e ADR-0139 (replay com CO embutido) implementados.

## Passos

1. **Reverter realocação** — `reverterRealocacaoAposCancelamentoVenda` em `RealocacaoEstoqueHubVendaService`.
2. **Integrar cancelamento** — chamar reversão e replay na loja de faturamento em `CancelarVendaMovimentacaoAdminService`.
3. **PM zerado** — ajustar `ReplayLinhaTempoEstoqueService` quando saldo kg = 0.
4. **Teste** — `test_cancelamento_venda_hub_restitui_estoque_com_co_embutido` em `VendaMovimentacaoTest`.

## Critério de conclusão

- Teste de cancelamento hub verde; snapshot hub/loja idêntico ao estado pré-venda.
- Suite `VendaMovimentacaoTest` verde.

## Riscos

- Múltiplas transferências parciais — mitigação: loop LIFO espelhando a realocação original.
