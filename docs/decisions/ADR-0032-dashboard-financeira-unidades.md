# ADR-0032: Dashboard financeira por unidade de negócio

**Data:** 2026-05-20
**Status:** Aceito
**Contexto:** Evolução da rota `/dashboard` com KPIs e gráficos no padrão Highdmin

## Contexto

A operação precisa de visão financeira mensal (vendas, devoluções, rentabilidade, doações e descartes) respeitando o vínculo do colaborador às unidades de negócio, com filtro opcional para uma ou mais unidades.

## Decisão

- **Período padrão:** dia 01 do mês corrente até a data de hoje (fuso `America/Sao_Paulo`).
- **Escopo:** movimentações `status_registro = ATIVO`; origem (`id_empresa_origem`) pertencente às empresas das unidades permitidas ao usuário.
- **Filtro de unidades:** multiselect opcional; vazio = todas as unidades permitidas; administrador/programador sem vínculo vê todas.
- **Cards (R$ e kg no mesmo card):**
  - *Faturado* = `SUM(valor_nf_total)` vendas.
  - *Devoluções* = `SUM(valor_devolucao_total)` devoluções (via venda de origem no escopo).
  - *Líquido* = faturado − devoluções (NF); kg = vendas − devoluções.
  - *Rentabilidade* = `SUM(resultado_movimentacao)` vendas + `SUM(resultado_devolucao)` devoluções.
  - *Descartado* = `SUM(valor_total_movimentacao)` e kg em descartes.
  - *Doado* = `SUM(valor_total_movimentacao)` e kg em doações.
- **Gráfico diário:** eixo X = cada dia do mês até hoje; séries R$: faturado, doado, descartado; série kg: vendido.
- **Pizza rentabilidade:** fatias *Resultado vendas* e *Resultado devoluções* (valores com sinal).
- **Permissão:** mesma rota `dashboard` (autenticado); sem permissão Spatie extra.

## Alternativas consideradas

- Endpoint JSON separado — rejeitado; dados embutidos na view como na v1.
- Período customizável — rejeitado no MVP; apenas mês corrente parcial.
- Incluir compras/transferências no gráfico — rejeitado; foco fluxo comercial e perdas.

## Consequências

- `DashboardStatsService` permanece para evoluções futuras; KPIs financeiros em `DashboardFinanceiroService`.
- Testes de feature cobrem escopo por unidade e totais dos cards.
