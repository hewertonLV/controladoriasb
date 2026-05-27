# ADR-0140: Cancelamento de venda HUB reverte realocação automática

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** Teste de cancelamento hub com CO embutido; [ADR-0061](ADR-0061-realocacao-hub-venda-sempre-loja-faturamento.md)

## Contexto

Toda venda com saída física no HUB dispara realocação que reduz/cancela transferências HUB→loja e grava ajustes diretos em `movimentacao_estoque` (`movimentacao_id` null). Ao cancelar só a venda e reprocessar o replay, o hub ficava com saldo fantasma (ex.: 20 kg) porque a realocação permanecia aplicada.

## Decisão

1. No cancelamento administrativo de venda com `id_unidade_negocio_estoque` HUB e faturamento distinto, **reverter a realocação** antes do replay: restaurar quantidades das transferências afetadas (ativas ou canceladas pelo motivo padrão de realocação) e reprocessar hub + loja.
2. Reprocessar também a unidade de **faturamento** além do HUB no cancelamento.
3. No replay integrado, preço médio consolidado zera quando saldo kg = 0 (evita PM residual após esgotar estoque).

## Alternativas consideradas

- **Somente replay na unidade HUB** — rejeitado; não desfaz realocação na loja/transferência.
- **Crédito/débito manual espelhando realocação** — rejeitado; conflita quando hub já está zerado após a venda; restaurar transferência + replay é suficiente.

## Consequências

- `RealocacaoEstoqueHubVendaService::reverterRealocacaoAposCancelamentoVenda` chamado em `CancelarVendaMovimentacaoAdminService`.
- Teste `test_cancelamento_venda_hub_restitui_estoque_com_co_embutido` cobre fluxo transferência + realocação + cancelamento.
