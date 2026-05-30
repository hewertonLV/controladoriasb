# ADR-0135: Venda com saída HUB — CO do faturamento embutido no custo de saída

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Captação e vendas com `id_unidade_negocio_estoque` HUB; refina [ADR-0063](ADR-0063-venda-hub-co-unidade-faturamento.md) e [ADR-0077](ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md)

## Contexto

Quando a fruta sai fisicamente de um HUB, o CO da unidade de faturamento ainda não está no preço médio do estoque do HUB. A operação pediu que esse CO componha o **custo da quantidade vendida**, sem alterar o PM do saldo remanescente nem o estoque do galpão.

## Decisão

- **Saída física = HUB** (`id_unidade_negocio_estoque` aponta para unidade `is_hub`):
  - Snapshot do CO vigente (R$/kg) da **unidade de faturamento** em `valor_custo_operacional`.
  - **`valor_custo_saida`** = `(PM HUB × kg) + (CO faturamento × kg)` — só para a quantidade da movimentação.
  - O **PM registrado na movimentação e no estoque remanescente** permanece o PM do HUB (sem incremento global).
  - O CO **não** é descontado de novo em `resultado_movimentacao` (já está no custo de saída).
  - `observacao` da movimentação descreve o motivo (saída HUB + CO da unidade de faturamento embutido).
- **Saída não-HUB:** mantém regras anteriores (galpão operacional, produção, etc.).

## Alternativas consideradas

- **CO só na margem (ADR-0063/0077)** — rejeitado para este fluxo; operação pediu custo de saída explícito.
- **Recalcular PM do HUB após a venda** — rejeitado; saldo remanescente não deve herdar o CO da parcela vendida.
- **CO da praça do cliente** — fora deste escopo; permanece faturamento conforme pedido operacional.

## Consequências

- Testes de venda HUB e captação com saída HUB atualizados.
- `FreteRateioMovimentacaoService` não desconta CO na margem quando saída HUB.
- UI de detalhe da venda exibe observação e detalhamento do CO embutido.
