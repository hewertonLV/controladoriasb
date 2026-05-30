# ADR-0167: Demanda automática de transferência não exclui manualmente

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Módulo Transferências — demandas geradas na conclusão da rota ([ADR-0157](ADR-0157-demandas-rota-sem-movimentacao-imediata.md))

## Contexto

Demandas de transferência criadas automaticamente ao concluir a rota (faturamento fiscal Cigam) não devem ser removidas pelo operador no módulo Transferências, para preservar o vínculo com a captação. Demandas manuais ([ADR-0160](ADR-0160-demanda-transferencia-manual-multi-fruta.md)) seguem outro ciclo e podem ser excluídas nas fases permitidas.

## Decisão

- **`captacao_lote_movimentacoes` com `id_captacao_rota` preenchido** (demanda automática da rota): **sem** botão Excluir na UI; `excluir()` retorna 422.
- Remoção só via **reabrir rota** na matriz ([ADR-0155](ADR-0155-status-demanda-rota-reabrir.md)), que soft-delete das demandas vinculadas.
- Download do arquivo Cigam na tela da demanda em Transferências usa rota do módulo movimentações, após status `INICIADO` ([ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md)).

## Alternativas consideradas

- Permitir excluir em `ABERTO` — rejeitado; quebra rastreio rota ↔ demanda fiscal.
- Hard delete na reabertura — rejeitado; [ADR-0163](ADR-0163-soft-delete-demandas-captacao.md).

## Consequências

- [PLAN-0167](../plans/PLAN-0167-demanda-transferencia-rota-nao-exclui-manual.md).
- Cards e detalhe da demanda exibem aviso de exclusão apenas via reabertura de rota.
