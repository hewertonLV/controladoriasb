# ADR-0043: Dashboard financeira — filtro por dia ou mês

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Dashboard financeira — período de consulta

## Contexto

A dashboard financeira aceitava apenas período mensal (dia 01 até hoje ou fim do mês). A operação pediu alternar entre visão do **mês** e de um **dia** específico.

## Decisão

- UI: seletor **Mês** | **Dia** com `input type="month"` ou `input type="date"` conforme o tipo; botão Buscar dispara AJAX como hoje.
- API: `GET /dashboard/dados` aceita `mes` (Y-m) ou `dia` (Y-m-d). Se `dia` vier preenchido, o período é aquele dia (00:00–23:59); senão mantém regra da ADR-0035.
- `DashboardPeriodo::resolver($mes, $dia)` centraliza início/fim e rótulo; resposta JSON inclui `periodo.tipo` (`mes`|`dia`) e `periodo.dia` quando aplicável.
- Olho de Fabio permanece só com filtro mensal (partial antigo).

## Alternativas consideradas

- Dois botões separados sem toggle — rejeitado: ocupa mais espaço e confunde com dois filtros ativos.
- Semana — fora do escopo deste pedido.

## Consequências

- Gráfico diário com filtro “dia” mostra um único ponto no eixo X.
- Polling e switches de unidade continuam enviando `mes` ou `dia` conforme o tipo selecionado.
