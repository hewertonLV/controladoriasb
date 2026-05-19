# PLAN-0015: Tela dedicada de CRUD de ICMS de frutas

**ADR:** [ADR-0015](../decisions/ADR-0015-tela-crud-icms-frutas.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Listagem, criação/edição manual e link de importação em `/admin/frutas/icms`.

## Passos

1. Permissões e `FrutaIcmsQuery`.
2. Controller, requests e rotas.
3. Views index/create/edit + menu.
4. Testes de listagem e criação.

## Critério de conclusão

Usuário com permissão acessa listagem, cria ICMS manualmente e abre importação pelo botão da tela.
