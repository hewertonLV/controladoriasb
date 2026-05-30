# ADR-0162: Demanda de transferência agregada por rota

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Conclusão de rota com saída física em HUB ≠ galpão de faturamento ([ADR-0157](ADR-0157-demandas-rota-sem-movimentacao-imediata.md))

## Contexto

Quando vários itens da mesma rota exigem transferência do HUB para o galpão operacional, criar uma demanda por fruta gera cards e movimentações fragmentadas. A operação fiscal/logística trata o abastecimento da rota como um único envio.

## Decisão

- **Uma demanda de transferência** por combinação `(lote, rota, unidade origem física)` quando a origem ≠ galpão de faturamento do lote.
- **Linhas da demanda:** tabela `captacao_lote_movimentacao_linhas` com `(fruta, qtd_um)` agregando todos os pedidos elegíveis da rota; mesma fruta em lojas distintas **soma** quantidades.
- **Cabeçalho** (`captacao_lote_movimentacoes`): `id_fruta` e `qtd_um` ficam nulos; quantidades ficam nas linhas.
- **Efetivação (NF):** uma única `transferencia_origem_id` compartilhada por todas as frutas da demanda (várias movimentações pareadas no mesmo grupo).
- **Referência NF:** `CAP-TR-{loteId}-R{rotaId}` (sem sufixo por fruta).
- **Idempotência:** reconclusão atualiza linhas existentes; remove linhas com quantidade zero.

## Alternativas consideradas

- **Manter uma demanda por fruta (ADR-0157)** — rejeitada; UX e operação pedem um card/transferência por rota.
- **Agrupar só na UI mantendo N registros** — rejeitada; duplicaria ciclo Cigam/NF e `transferencia_origem_id`.

## Consequências

- [PLAN-0162](../plans/PLAN-0162-demanda-transferencia-rota-agregada.md).
- Atualiza [ADR-0157](ADR-0157-demandas-rota-sem-movimentacao-imediata.md) na agregação: de `(lote, rota, origem, fruta)` para `(lote, rota, origem)` + linhas.
- Migration consolida registros legados por rota/origem.
