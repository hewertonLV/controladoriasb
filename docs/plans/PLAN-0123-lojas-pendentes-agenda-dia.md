# PLAN-0123: Lojas pendentes por agenda de criação do dia

**ADR:** [ADR-0123](../decisions/ADR-0123-lojas-pendentes-agenda-dia.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Tela Lojas pendentes com lista por agenda do dia, status de captação e filtros data/carteira.

## Passos

1. **Query** — `LojasPendentesCaptacaoQuery` + carteiras acessíveis ao usuário.
2. **Controller/view** — renomear UI, filtros, destaque visual.
3. **Menu/rota** — Lojas pendentes; redirect da URL antiga.
4. **Testes** — programação, iniciou vs pendente, escopo de carteira.

## Critério de conclusão

Testes verdes; tela lista programadas do dia com badges pendente/iniciou.
