# PLAN-0043: Dashboard financeira — filtro por dia ou mês

**ADR:** [ADR-0043](../decisions/ADR-0043-dashboard-financeira-filtro-dia-ou-mes.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir na dashboard financeira consultar totais e gráficos por mês (comportamento atual) ou por um dia específico.

## Pré-requisitos

- ADR-0043 aceita
- `DashboardPeriodo` e endpoint `/dashboard/dados` existentes

## Passos

1. Estender `DashboardPeriodo` com resolução por `dia` (Y-m-d).
2. Validar `dia` em `DashboardIndexRequest` e repassar no controller/serviço.
3. Partial e JS: toggle Mês/Dia + parâmetros na URL de dados.
4. Testes unitários e de feature.

## Critério de conclusão

- Buscar com `dia=hoje` retorna movimentações só daquele dia; `dia=ontem` retorna zero se não houver venda ontem.
- Buscar com `mes` mantém comportamento da ADR-0035.

## Riscos

- Cache do JS antigo — usuário pode precisar refresh forçado após deploy.
