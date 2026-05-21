# PLAN-0032: Dashboard financeira por unidade

**ADR:** [ADR-0032](../decisions/ADR-0032-dashboard-financeira-unidades.md)
**Data:** 2026-05-20
**Status:** Concluído

## Objetivo

Entregar dashboard financeira na rota `/dashboard` com cards, gráfico diário do mês e pizza de rentabilidade, filtrada por unidades do usuário.

## Pré-requisitos

- Movimentações de venda, devolução, doação e descarte com campos de valor e kg preenchidos.
- ApexCharts já presente em `public/assets/vendor/apexcharts`.

## Passos

1. **Serviço** — `DashboardFinanceiroService` com cards, séries diárias e pizza.
2. **Request** — validação de `unidades[]` e interseção com escopo do usuário.
3. **Controller + view** — filtros, cards Highdmin, gráficos Apex.
4. **JS** — `dashboard-financeiro.js` com dados do backend.
5. **Testes** — feature com venda/devolução e escopo por unidade.

## Critério de conclusão

- Dashboard exibe 6 cards com R$ e kg do mês corrente.
- Gráfico diário e pizza renderizam com dados reais.
- Filtro multiselect restringe totais às unidades escolhidas.
- Testes passam.

## Riscos

- Devolução fora do escopo de empresa — mitigado via `vendaOrigem.id_empresa_origem`.
- Pizza com valores negativos — exibir legenda com sinal; fatias usam valor absoluto com cor por sinal.
