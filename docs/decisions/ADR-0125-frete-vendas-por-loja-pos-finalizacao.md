# ADR-0125: Frete de vendas por loja após vendas finalizadas

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Matriz do lote de captação; abas de frete ([ADR-0072](ADR-0072-vinculo-frete-pos-transferencia-lote.md))

## Contexto

O frete de **transferência HUB × CD** é vinculado na etapa `AGUARDANDO_VINCULO_FRETE`. Após **Vendas finalizadas**, o operador precisa vincular (opcionalmente) o frete das **vendas por loja**, com visão dos itens vendidos de cada pedido.

## Decisão

- Renomear a aba existente para **Frete HUB x CD** (`aba=frete-hub`): apenas transferências do lote.
- Nova aba **Frete Vendas** (`aba=frete-vendas`), visível em `VENDAS_FINALIZADAS`.
- Listagem agrupada por **loja (cliente)** com itens do pedido (quantidade &gt; 0) e um **select de frete ABERTO** por loja.
- Vínculo atualiza todas as movimentações ativas da NF de captação (`CAP-{data}-{lote}-{cliente}`) e recalcula rateio ([ADR-0003](../adr/0003-rateio-frete-compartilhado-entre-movimentacoes.md)).
- Frete **opcional** por loja; remover vínculo zera rateio na nota.
- O formulário antigo “frete por fruta” na aba de transferência é **removido** da UI; o pré-vínculo por fruta (`captacao_lote_frete_linhas`) permanece só para geração automática de vendas.

## Alternativas consideradas

- **Manter frete por fruta na mesma aba** — rejeitado; confunde transferência com venda e não reflete agrupamento por loja.
- **Nova tabela `captacao_lote_frete_pedidos`** — rejeitado; frete já está em `movimentacoes` da `venda_nota`.
- **Editar venda com versionamento completo** — rejeitado; vínculo de frete segue padrão leve de [ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md).

## Consequências

- [PLAN-0125](../plans/PLAN-0125-frete-vendas-por-loja-pos-finalizacao.md).
- Rota `POST .../fretes/venda-loja` na matriz.
- Testes de matriz e redirect da rota legada de fretes.
