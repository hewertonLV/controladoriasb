# ADR-0075: Transferência gerencial automática na validação Lucas

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Resposta ao item A (PDF §7 vs pacote); [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)

## Contexto

A operação não adotará, neste fluxo, transferência **somente fiscal** no Cigan sem movimento gerencial no SB. Quando **Lucas** confirma a validação das transferências, o sistema deve **criar automaticamente** as movimentações de transferência interna que **abastecem os galpões de destino**, conforme o romaneio de abastecimento (parcela “a receber”).

## Decisão

- **Validar transferências** (Lucas): dispara criação automática de transferências gerenciais ([ADR-0065](ADR-0065-transferencia-sem-confirmacao-recebimento.md)) origem física informada → galpão destino do lote; quantidades do romaneio.
- **Cigan:** arquivo gerado em **Iniciar transferência** cobre a parte **fiscal/oficial**; importação no Cigan é paralela e **não substitui** o lançamento gerencial no SB.
- **Escopo de Lucas:** vê e opera romaneios/lotes apenas das **unidades de faturamento** (e galpões ligados) às quais o usuário está **vinculado** + permissão de transferência do lote.
- Romaneio manual ([ADR-0074](ADR-0074-romaneio-manual-abastecimento-sem-captacao.md)) e captação com pedidos usam o **mesmo** passo de validação.

## Alternativas consideradas

- **Transferência fiscal no Cigan sem crédito no galpão gerencial** — rejeitado para este fluxo; estoque do galpão não refletiria a reposição.
- **Lucas digita transferências manualmente** — rejeitado; já coberto por validação automática.

## Consequências

- Integrar em [PLAN-0067](PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md); filtro de listagem por vínculo usuário↔UN faturamento.
- Realocação HUB ([ADR-0061](ADR-0061-realocacao-hub-venda-sempre-loja-faturamento.md)) permanece para vendas com saída HUB em cenários legados/importação; fluxo novo prioriza abastecimento do galpão antes da venda.
