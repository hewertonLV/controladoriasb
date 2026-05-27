# PLAN-0034: Filtro switches AJAX na dashboard (mesmo da ADR)

**ADR:** [ADR-0034](../decisions/ADR-0034-dashboard-filtro-switches-ajax.md)
**Data:** 2026-05-21
**Status:** Concluído

## Objetivo

Trocar multiselect por switches e atualizar KPIs/gráficos sem recarregar a página.

## Passos

1. Endpoint `dashboard.dados` + ajuste `unidadeIdsFiltro`.
2. View com switches e data-attributes nos cards.
3. `dashboard-financeiro.js` com fetch e atualização de gráficos.
4. Testes do endpoint JSON.

## Critério de conclusão

- Desligar unidade zera totais via AJAX; religar restaura valores sem F5.
