# PLAN-0035: Período mensal nas dashboards (mesmo da ADR)

**ADR:** [ADR-0035](../decisions/ADR-0035-periodo-mes-dashboards.md)
**Data:** 2026-05-21
**Status:** Concluído

## Objetivo

Campo Month + Buscar em ambas dashboards, período 01→hoje (mês atual) ou mês fechado.

## Passos

1. `DashboardPeriodo` compartilhado.
2. Ajustar services, requests, controllers.
3. Partial Blade + JS das duas telas.
4. Testes unitários e feature.

## Critério de conclusão

- Buscar com mês passado retorna totais só daquele mês; Olho de Deus lista alertas do intervalo.
