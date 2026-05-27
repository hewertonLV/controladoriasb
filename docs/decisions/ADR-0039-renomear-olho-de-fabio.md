# ADR-0039: Renomear dashboard Olho no Gado para Olho de Fabio

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Nomenclatura solicitada pela operação

## Contexto

A dashboard de monitoramento foi renomeada de “Olho no Gado” para “Olho de Fabio”.

## Decisão

- Rota `/olho-de-fabio`, permissão `olho-de-fabio.visualizar`, config `config/olho_de_fabio.php`.
- Redirect 301 de `/olho-de-deus`, `/olho-no-gado` e respectivos `/poll` para as novas URLs.
- Classes PHP (`OlhoDeDeus*`) inalteradas; apenas superfície HTTP/UI renomeada.

## Alternativas consideradas

- Manter slug `olho-no-gado` só no texto — rejeitado; alinhar rota e permissão ao nome exibido.

## Consequências

- Grupos com `olho-no-gado.visualizar` precisam de `olho-de-fabio.visualizar` após re-sync do seeder.
