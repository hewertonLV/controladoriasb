# ADR-0123: Lojas pendentes por agenda de criação do dia

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Evolução da consulta «sem pedido» ([ADR-0084](ADR-0084-captacao-dia-carteira-agenda-cliente.md))

## Contexto

A operação precisa ver, para uma data consultável, quais lojas têm **programação de criação de pedido** naquele dia da semana (`CRIACAO_PEDIDO` em `cliente_captacao_agenda`) e se já **iniciaram** pedido na captação da data (registro em `pedidos` em lote `CAPTACAO_PEDIDOS` da carteira).

## Decisão

- Renomear a tela para **Lojas pendentes**.
- Listar **todas** as lojas programadas para o dia da semana da data, não só as sem pedido.
- Destacar visualmente: **pendente** (sem pedido no lote do dia) vs **iniciou** (já existe pedido na captação da data/carteira).
- Filtros: **data** (padrão hoje) e **carteira** (padrão **todas** as carteiras ativas acessíveis ao usuário via vínculo em faturamento ou galpão).
- Pedido «iniciado» = existe `pedidos` em qualquer lote `CAPTACAO_PEDIDOS` da mesma `(data_referencia, carteira)`, independente do status do lote.

## Alternativas consideradas

- Manter só lojas sem pedido — rejeitado; operação precisa ver também as que já começaram.
- Exigir lote em andamento — rejeitado; consulta deve funcionar mesmo sem lote aberto.

## Consequências

- [PLAN-0123](../plans/PLAN-0123-lojas-pendentes-agenda-dia.md).
- Substitui o comportamento da rota `consulta.sem-pedido` (mantida como alias de URL).
