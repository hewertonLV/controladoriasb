# ADR-0034: Filtro de unidades na dashboard com switches e AJAX

**Data:** 2026-05-21
**Status:** Aceito
**Contexto:** Substituir multiselect com submit por filtro dinâmico sem refresh

## Contexto

O filtro de unidades usava `<select multiple>` e recarregava a página. A operação pediu switches com todas as unidades permitidas ligadas por padrão e atualização imediata dos cards e gráficos.

## Decisão

- UI: `form-switch` por unidade permitida; todas ativas na carga inicial.
- Dados: endpoint JSON `GET /dashboard/dados` com `unidades[]` ou `sem_unidades=1` quando nenhuma switch ativa.
- Cliente: debounce 350ms no `change`; atualiza cards e ApexCharts via `updateOptions` (sem novo request global).
- Carga isolada: consulta só quando o usuário altera switches na dashboard (não afeta outras rotas).

## Alternativas consideradas

- Manter GET com refresh — rejeitado pelo requisito explícito.
- WebSocket — rejeitado: complexidade desnecessária para filtro manual.

## Consequências

- `forUser($user, [])` retorna totais zerados; `null` no filtro continua significando “todas” apenas na carga inicial sem query.
