# ADR-0035: Período mensal com campo Month nas dashboards

**Data:** 2026-05-21
**Status:** Aceito
**Contexto:** Filtro de mês e botão Buscar nas dashboards principal e Olho de Fabio

## Contexto

As dashboards usavam sempre o mês corrente implícito. A operação pediu seletor `type="month"` (Highdmin) com botão Buscar e, no Olho de Fabio, dados do dia 01 até o dia atual no mês selecionado.

## Decisão

- `DashboardPeriodo::resolver(mes?)`: início = dia 01 do mês; fim = hoje se mês atual, senão último dia do mês.
- Dashboard financeira: parâmetro `mes` em `GET /dashboard/dados`; botão Buscar dispara atualização AJAX.
- Olho de Fabio: `data_movimentacao` dentro do período; `carga_inicial=1` no Buscar varre o mês; polls seguintes só novidades (`since`) no mesmo período.

## Alternativas consideradas

- Alterar mês recarregando a página — rejeitado pelo requisito sem refresh.

## Consequências

- Meses futuros retornam período vazio até existir movimentação.
- Carga inicial limitada a 100 movimentações por configuração.
