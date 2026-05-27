# PLAN-0072: Vínculo de frete após transferências do lote

**ADR:** [ADR-0072](../decisions/ADR-0072-vinculo-frete-pos-transferencia-lote.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Tela para Lucas vincular fretes ABERTOS às transferências geradas na validação do lote, com conclusão permitida sem frete nas linhas que não usarem.

## Pré-requisitos

- [PLAN-0067](PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md) passo Validar transferências (movimentações criadas e vinculadas ao lote).
- Módulo de fretes e `POST vincular-frete` existente ([ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md)).

## Passos

1. Status `AGUARDANDO_VINCULO_FRETE` após validação gerencial.
2. Tela admin — lista transferências do lote; select fretes ABERTOS; salvar por linha (reuso service/rateio).
3. Remover vínculo — permitido; recalcula rateio.
4. Botão **Concluir etapa de frete** — sem obrigatoriedade de frete por linha; → `TRANSFERENCIA_FINALIZADA`.
5. Bloquear Jefferson e complementar até concluir etapa.
6. Testes — concluir sem frete; vincular e rateio; só fretes ABERTOS no select.

## Critério de conclusão

- Lucas valida transferências → cai na tela de frete → pode concluir com zero vínculos ou vínculos parciais.
- Romaneio só trava após Concluir etapa de frete.

## Riscos

- Muitas transferências no lote — paginação ou agrupamento por origem/fruta.

## Ordem

Integrar no [PLAN-0067](PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md) entre validar e Jefferson.
