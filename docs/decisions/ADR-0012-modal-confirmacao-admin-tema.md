# ADR-0012: Modal de confirmação admin no padrão do tema

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Ações destrutivas (inativar, cancelar, desativar) usavam `window.confirm()` nativo, fora do visual Highdmin.

## Contexto

Listagens e telas de movimentação pediam confirmação via diálogo do navegador, inconsistente com modais coloridas do tema (Danger / Success header).

## Decisão

Centralizar confirmações em `<x-admin.confirm-modal>` + `admin-confirm.js`, interceptando formulários com `data-confirm*`. Variantes de cabeçalho: `danger` (inativar/cancelar), `success` (ativar/reativar), `warning` (reset de senha). Campo opcional no modal via `data-confirm-prompt` para motivo (ex.: cancelamento de item de venda).

## Alternativas consideradas

- Manter `confirm()` — fora do tema e sem customização.
- Biblioteca externa (SweetAlert) — dependência extra desnecessária com Bootstrap já no projeto.
- Modal por tela — duplicação de markup e JS.

## Consequências

- Novas confirmações devem usar `data-confirm` no `<form>`, não `onsubmit="return confirm(...)"`.
- Layout principal inclui uma única instância da modal.
- Testes de feature podem assertar presença de `#adminConfirmModal` nas páginas admin autenticadas.
