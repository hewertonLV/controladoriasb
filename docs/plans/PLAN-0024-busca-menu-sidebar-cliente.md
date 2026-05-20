# PLAN-0024: Busca de itens do menu no cliente (mesmo da ADR)

**ADR:** [ADR-0024](../decisions/ADR-0024-busca-menu-sidebar-cliente.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir filtrar e abrir páginas do menu pelo campo de busca no topbar.

## Pré-requisitos

- Layout com `sidebar.blade.php` e `topbar.blade.php` carregados em páginas autenticadas.

## Passos

1. **Modal** — adicionar área de resultados e estados vazio/dica em `topbar.blade.php`.
2. **Script** — criar `menu-search.js` indexando `.side-nav` e filtrando no input.
3. **Assets** — incluir script em `scripts.blade.php`.
4. **Teste** — garantir presença do modal e do script no layout autenticado.

## Critério de conclusão

- Digitar no modal lista itens do menu e abre a rota ao clicar ou Enter.

## Riscos

- Menu muito grande no futuro — mitigar com limite de 20 resultados já aplicado no script.
