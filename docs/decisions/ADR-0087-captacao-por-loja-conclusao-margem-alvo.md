# ADR-0087: Captação por loja, conclusão por loja e margem alvo

**Data:** 2026-05-25
**Status:** Aceito
**Contexto:** UX alternativa à matriz; gate para avançar pipeline

## Contexto

Operação precisa captar pedidos **loja a loja** (cards), ver preços de referência e concluir cada loja antes de finalizar o dia. A matriz permanece para visão agregada.

## Decisão

### Navegação

1. **Carteiras** — cards dos lotes `CAPTACAO_PEDIDOS` em `CAPTACAO_EM_ANDAMENTO` (filtro por data).
2. **Lojas da carteira** — cards de **todas** as lojas com `id_captacao_carteira` da carteira (com ou sem frutas vinculadas); aviso «Sem frutas vinculadas» quando aplicável.
3. **Detalhe da loja** — último pedido da captação anterior + pedido do dia (qty, preços).

### Estado visual da card da loja

| Estado | Borda | Critério |
|--------|-------|-----------|
| Não iniciado | Branca | Nenhum item com `quantidade > 0` |
| Em andamento | Laranja | ≥1 item com qty > 0 e `captacao_concluida = false` |
| Concluído | Verde | `captacao_concluida = true` (exige ≥1 item com qty > 0) |

Card concluída exibe **rentabilidade** agregada do pedido (% e R$ sobre itens com qty > 0).

### Conclusão (`pedidos.captacao_concluida`)

- Toggle em **matriz** (botão fim da linha) e em **captação por loja** (detalhe + card).
- Só com lote `CAPTACAO_EM_ANDAMENTO`; pode marcar e desmarcar livremente nesse status.
- **Finalizar captação do faturamento** ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md)) só se **todas** as lojas elegíveis da carteira (frutas vinculadas) estiverem concluídas nos lotes da data.

### Margem alvo no cliente

- Campo `clientes.percentual_margem_alvo` (0–100, nullable): margem desejada **sobre preço de venda**.
- `preco_ideal = custo / (1 − alvo/100)` quando custo e alvo válidos ([ADR-0073](ADR-0073-captacao-app-custo-preco-margem-um.md)).

## Alternativas consideradas

- Conclusão só na matriz — rejeitado; operação usa fluxo por loja.
- Finalizar com lojas não concluídas — rejeitado.

## Consequências

- [PLAN-0087](../plans/PLAN-0087-captacao-por-loja-conclusao-margem-alvo.md).
