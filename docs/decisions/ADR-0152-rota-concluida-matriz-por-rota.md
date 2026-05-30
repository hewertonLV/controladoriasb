# ADR-0152: Concluir rota na aba Por rota

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Matriz de captação — aba Por rota

## Contexto

Operadores precisam fechar uma rota na matriz para impedir novos vínculos de lojas e alterações de ordem/motorista/veículo, sem travar as demais rotas do lote.

## Decisão

- Campo `concluida` em `captacao_lote_rotas` (por par lote+rota).
- Botão **Concluir** no cabeçalho de cada rota na aba Por rota; **Reabrir** quando concluída.
- Só é possível concluir a rota com **motorista** preenchido, **veículo** selecionado, **ordem de carregamento** definida e **captação concluída** em cada loja com quantidade na rota.
- **Reabrir captação** de uma loja só é permitido enquanto a rota dela não estiver concluída (`captacao_lote_rotas.concluida`).
- Rota concluída: bloqueia vínculo de lojas (`id_captacao_rota`), ordem de carregamento, motorista e veículo dessa rota; outras rotas seguem editáveis enquanto o lote permitir vínculo.
- Botão Concluir permanece desabilitado na UI enquanto houver pendências; tooltip lista o que falta.
- Reabrir zera `concluida` e restaura edição, respeitando `permiteEdicaoVinculoRota()` do status do lote.

## Alternativas consideradas

- **Concluir no nível do lote (pipeline)** — já existe para etapa global; não substitui fechamento operacional por rota.
- **Flag em `captacao_rotas`** — rejeitado; configuração é por lote ([ADR-0134](ADR-0134-motorista-veiculo-por-lote-rota.md)).

## Consequências

- [PLAN-0152](../plans/PLAN-0152-rota-concluida-matriz-por-rota.md).
- Complementa [ADR-0095](ADR-0095-rota-ordem-editaveis-com-pedido-concluido.md): conclusão da **rota** bloqueia ordem e reabertura da loja; conclusão da **loja** não bloqueia rota/ordem até a rota ser concluída, mas a rota exige todas as lojas concluídas.
