# PLAN-0146: Hub de módulos e contexto operacional do vendedor

**ADR:** [ADR-0146](../decisions/ADR-0146-hub-modulos-vendedor.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Entregar hub de módulos, sessão de contexto e layouts sem sidebar para o perfil operacional (vendedor).

## Pré-requisitos

- Autenticação Spatie Permission existente.
- Rotas admin de captação, transferências e vendas.

## Passos

1. **Enum e serviço** — `AppModulo`, `ModuloHubService` com regras de visibilidade e URLs de entrada.
2. **Controller e rotas** — `ModulosController`, grupo `/modulos`, redirects pós-login.
3. **Layouts** — `layouts.modulos` (hub), ajuste `layouts.app` para sidebar condicional.
4. **Views** — `modulos/index` com cards; homes placeholder por módulo operacional.
5. **Testes** — hub, entrada em módulo, sidebar oculta no contexto operacional.

## Critério de conclusão

- Usuário autenticado cai em `/modulos`.
- Cards respeitam permissões; entrar define `app_modulo` e redireciona.
- Vendedor (só permissões operacionais) não vê card Administrador nem sidebar nas telas do módulo.

## Riscos

- Acesso direto a `/dashboard` por URL — mitigar ocultando sidebar ou redirecionando perfil sem Administrador.
