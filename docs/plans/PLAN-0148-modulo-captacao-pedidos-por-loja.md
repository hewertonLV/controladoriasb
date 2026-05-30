# PLAN-0148: Entrada do módulo Captação em Captação por loja

**ADR:** [ADR-0148](../decisions/ADR-0148-modulo-captacao-pedidos-por-loja.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Hub e módulo Captação abrem em Captação por loja, com criação de captação na mesma tela.

## Passos

1. **Hub** — `ModuloHubService` aponta para `pedidos-por-loja.carteiras`.
2. **View** — formulário de abrir captação na tela de carteiras (partial compartilhado).
3. **Redirect** — pós-criação no contexto módulo Captação vai para lojas do lote.
4. **Testes** — hub, carteiras e redirect.

## Critério de conclusão

- Entrar no módulo Captação abre Captação por loja.
- Formulário cria captação e leva às lojas quando `app_modulo=captacao`.
