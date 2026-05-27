# ADR-0138: Gerar vendas da captação por loja (idempotência parcial)

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** Lote #14 — vendas aparentemente incompletas na aba Arquivo Cigam Venda

## Contexto

`GerarVendasCaptacaoLoteService` abortava a geração quando **qualquer** vínculo `VENDA_NOTA` existia no lote. Se a primeira execução criasse vendas só para parte das lojas (pedido sem itens na hora, loja incluída depois do upload da NF), as demais nunca eram geradas.

## Decisão

- Idempotência **por loja/pedido**: considerar sincronizado apenas quando existir NF `CAP-{data}-{lote}-{cliente}`, vínculo em `captacao_lote_movimentacoes` e quantidade de movimentações ativas igual aos itens com quantidade > 0.
- Reexecução (`sincronizar-vendas-pendentes` ou nova chamada a `executar`) gera somente lojas pendentes.
- Exibir resumo «Movimentação de venda no SB» na aba Arquivo Cigam Venda após NF enviada.

## Alternativas consideradas

- Manter aborto global — rejeitado; deixa lojas sem movimentação permanentemente.
- Regenerar todas as vendas do lote — rejeitado; risco de duplicar NF e estoque.

## Consequências

- [PLAN-0138](../plans/PLAN-0138-gerar-vendas-captacao-por-loja.md).
- Lotes já afetados: usar botão **Gerar vendas pendentes** na matriz.
