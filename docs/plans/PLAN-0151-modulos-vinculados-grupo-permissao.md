# PLAN-0151: Módulos do hub vinculados a grupos de permissão

**ADR:** [ADR-0151](../decisions/ADR-0151-modulos-vinculados-grupo-permissao.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Configurar módulos do hub por grupo em Grupos de Permissões e usar essa fonte no `ModuloHubService`.

## Pré-requisitos

- Enum `AppModulo` e hub existentes.

## Passos

1. **Migration** — `role_app_modulos`.
2. **RoleModuloService** — sync e consulta por role/usuário.
3. **ModuloHubService** — remover inferência por permissão/role fixa.
4. **Grupos de Permissões** — formulário, requests, controller, seeder.
5. **Testes** — seeder, hub, CRUD de grupo.

## Critério de conclusão

- Marcar módulos em um grupo faz o usuário vê-los no hub.
- Testes PHPUnit relacionados passam.

## Riscos

- Grupo só com módulo sem permissão de rota — mitigação: documentar que módulo + permissão são complementares.
