# PLAN-0147: Grupo de permissão Vendedor

**ADR:** [ADR-0147](../decisions/ADR-0147-grupo-permissao-vendedor.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Disponibilizar role `Vendedor` com permissões padrão para Captação, Transferência e Venda.

## Pré-requisitos

- ADR-0146 (hub de módulos).
- `PermissionSeeder` e `RoleSeeder` existentes.

## Passos

1. **Enum** — adicionar `Roles::VENDEDOR`.
2. **Permissões padrão** — `Permissions::permissoesGrupoVendedor()`.
3. **Seeder** — `RoleSeeder` atribui permissões ao grupo Vendedor.
4. **Hub** — `ModuloHubService` reconhece role Vendedor nos três módulos.
5. **Testes** — seeder e hub com usuário Vendedor.

## Critério de conclusão

- Role `Vendedor` criada pelo seeder.
- Permissões padrão aplicadas idempotentemente.
- Hub lista Captação, Transferência e Venda; oculta Administrador.

## Riscos

- Permissões insuficientes para fluxo real — ajustar lista em `permissoesGrupoVendedor()` conforme telas forem implementadas.
