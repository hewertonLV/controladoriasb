# PLAN-0051: Calendário de fretes na logística

**ADR:** [ADR-0051](../decisions/ADR-0051-calendario-fretes-logistica.md)
**Data:** 2026-05-21
**Status:** Concluído

## Objetivo

Página Calendário em Logística com FullCalendar e filtro por mês.

## Passos

1. Copiar bundle FullCalendar do tema.
2. Service + rotas + controller + view + JS.
3. Item de menu e testes feature.

## Critério de conclusão

- Buscar mês retorna apenas fretes cadastrados naquele intervalo; menu Calendário visível com `fretes.visualizar`.
