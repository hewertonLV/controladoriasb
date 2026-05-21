# ADR-0038: Renomear dashboard Olho de Deus para Olho no Gado

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Nomenclatura solicitada pela operação

## Contexto

O produto interno “Olho de Deus” passou a ser chamado “Olho no Gado” em menu, títulos e rotas.

## Decisão

- Rota `/olho-no-gado`, permissão `olho-no-gado.visualizar`, config `config/olho_no_gado.php`.
- Redirect 301 de `/olho-de-deus` e `/olho-de-deus/poll` para as novas URLs (compatibilidade de favoritos).
- Classes PHP (`OlhoDeDeus*`) mantidas para evitar refactor amplo; apenas superfície HTTP/UI renomeada.

## Alternativas consideradas

- Manter slug `olho-de-deus` só no texto — rejeitado; usuário pediu troca de nome completa.
- Renomear classes PHP — adiado; sem ganho funcional imediato.

## Consequências

- Grupos de permissão com `olho-de-deus.visualizar` precisam receber `olho-no-gado.visualizar` (re-sync do seeder ou ajuste manual).
- ADR-0033 permanece histórica; comportamento descrito ali vale para Olho no Gado.
