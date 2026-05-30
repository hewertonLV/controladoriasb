# PLAN-0149: Topbar do módulo Captação sem sidebar

**ADR:** [ADR-0149](../decisions/ADR-0149-topbar-modulo-captacao.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Topbar full-width no módulo Captação com botões Módulos/Criar Captação à esquerda, título centralizado e modal de abertura de captação.

## Pré-requisitos

- ADR-0146 e ADR-0148 implementados.
- Sessão `app_modulo` ao entrar no módulo.

## Passos

1. **CSS operacional** — zerar margem de `.app-topbar` e `.page-content` em `modulo-operacional`.
2. **Partial topbar-captacao** — grid três colunas, botões, modal com `_abrir-captacao-form`.
3. **View composer** — carteiras ativas para o modal quando `app_modulo = captacao`.
4. **Carteiras** — ocultar formulário inline no contexto do módulo.
5. **Testes** — `ModuloHubTest` e `CaptacaoPedidoPorLojaTest` cobrindo topbar e modal.

## Critério de conclusão

- Vendedor em qualquer tela do módulo Captação vê topbar edge-to-edge, botões à esquerda, título ao centro, modal funcional.
- Testes PHPUnit relacionados passam.

## Riscos

- Performance do composer em todas as views admin — mitigação: query simples só quando módulo Captação ativo.
