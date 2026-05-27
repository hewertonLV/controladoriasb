# PLAN-0039: Renomear Olho no Gado para Olho de Fabio

**ADR:** [ADR-0039](../decisions/ADR-0039-renomear-olho-de-fabio.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Menu, títulos e rotas exibem “Olho de Fabio”.

## Passos

1. Permissão e grupo em `Permissions.php`.
2. Rotas, redirects legados, config `olho_de_fabio.php`.
3. View, JS, sidebar, testes.
4. Remover arquivos `olho_no_gado` / `olho-no-gado`.

## Critério de conclusão

- Rota `olho-de-fabio.*` funcional; testes `OlhoDeDeusTest` verdes.

## Riscos

- Permissões antigas nos grupos — reatribuir após deploy.
