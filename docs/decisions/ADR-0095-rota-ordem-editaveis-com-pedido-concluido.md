# ADR-0095: Rota e ordem editáveis com pedido concluído

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz de captação — abas Rotas e Por rota

## Contexto

A conclusão da captação por loja bloqueava quantidade, preço e número do pedido, mas a operação de logística precisa **ajustar rota e ordem de carregamento** mesmo após a loja ter sido marcada como concluída.

## Decisão

- `captacao_concluida` **não bloqueia** `id_captacao_rota` nem `ordem_carregamento`.
- Quantidade, preço e `numero_pedido` continuam bloqueados enquanto a loja estiver concluída (comportamento existente).
- UI das abas Rotas e Por rota mantém selects habilitados independentemente da conclusão.

## Alternativas consideradas

- **Exigir reabrir pedido** — rejeitado; atrasa ajustes de carregamento após fechamento comercial da loja.
- **Liberar tudo ao concluir** — rejeitado; conclusão deve proteger quantidades e preços captados.

## Consequências

- [PLAN-0095](../plans/PLAN-0095-rota-ordem-editaveis-com-pedido-concluido.md).
