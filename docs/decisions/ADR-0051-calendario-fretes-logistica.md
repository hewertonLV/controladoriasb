# ADR-0051: Calendário de fretes na logística

**Data:** 2026-05-21
**Status:** Aceito
**Contexto:** Menu Calendário na aba Logística com visualização mensal de fretes

## Contexto

A operação precisa enxergar fretes distribuídos no mês, com filtro para outros meses, usando o calendário do tema Highdmin (FullCalendar).

## Decisão

- Nova rota `admin/fretes/calendario` com permissão `fretes.visualizar`.
- Cada frete vira evento no dia de `created_at` (data de cadastro já usada na listagem).
- Intervalo do filtro: mês civil completo (dia 01 ao último dia), independente do dia atual.
- Situação ABERTA = verde; ENCERRADA = cinza. Clique abre modal com detalhes e link para editar quando permitido.

## Alternativas consideradas

- Data da primeira movimentação vinculada — rejeitada por exigir join e não refletir cadastro do frete.
- Nova permissão só para calendário — rejeitada; reutiliza visualização de fretes.

## Consequências

- Fretes importados em lote aparecem no dia da importação, não na data operacional da carga.
