# PLAN-0012: Modal de confirmação admin no padrão do tema

**ADR:** [ADR-0012](../decisions/ADR-0012-modal-confirmacao-admin-tema.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Substituir `window.confirm()` por modal Bootstrap com cabeçalho colorido em todas as ações administrativas que exigem confirmação.

## Pré-requisitos

- Bootstrap 5 e ícones Remix já carregados no layout admin.

## Passos

1. **Componente** — criar `confirm-modal.blade.php` e incluir no `layouts/app.blade.php`.
2. **JavaScript** — criar `admin-confirm.js` com variantes e suporte a `data-confirm-prompt`.
3. **Migrar views** — trocar `onsubmit`/`onclick` confirm em unidades, veículos, usuários e movimentações.
4. **Testes** — validar listagem admin inclui a modal e fluxos POST continuam funcionando.

## Critério de conclusão

Nenhum `confirm()` em `resources/views`; inativar unidade abre modal danger do tema; testes de módulos afetados passam.

## Riscos

- Conflito com `form-submit-guard` — mitigado com interceptação em capture e flag `data-confirm-approved`.
