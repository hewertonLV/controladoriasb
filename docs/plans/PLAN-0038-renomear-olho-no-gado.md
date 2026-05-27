# PLAN-0038: Renomear Olho de Deus para Olho no Gado

**ADR:** [ADR-0038](../decisions/ADR-0038-renomear-olho-no-gado.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Superfície do produto (rotas, permissão, menu, textos) exibe “Olho no Gado”.

## Pré-requisitos

- ADR-0038 aceita

## Passos

1. Permissão e grupo em `Permissions.php`.
2. Rotas, redirect legado, config `olho_no_gado.php`.
3. View, JS, sidebar, testes.
4. Remover `config/olho_de_deus.php`.

## Critério de conclusão

- Menu e título “Olho no Gado”; rota `olho-no-gado.*` funcional.
- Redirect de URLs antigas.
- `OlhoDeDeusTest` atualizado e verde.

## Riscos

- Permissões antigas nos grupos — reatribuir após deploy.
