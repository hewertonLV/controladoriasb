# ADR-0128: Remover loja da matriz durante captação

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** Matriz de captação ([ADR-0071](ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md)); inclusão de loja via select

## Contexto

O operador pode incluir a loja errada na matriz. É necessário desfazer sem recarregar a página, espelhando o fluxo de adicionar loja.

## Decisão

- Select **Remover loja** na mesma faixa da matriz, listando apenas lojas **já incluídas** no lote.
- Permitido somente com lote em `CAPTACAO_EM_ANDAMENTO` (mesma regra de adicionar).
- Remove o **pedido** do lote (soft delete) e seus **itens** (delete físico); registra histórico `remover_matriz`.
- Readicionar a mesma loja **restaura** o pedido soft-deleted (unique `pedido_lote_cliente_uq`), com rota/número/ordem/conclusão zerados — não insere linha nova.
- Resposta JSON com snapshot da matriz (igual adicionar) para atualizar a grade sem refresh.
- Permissão: `captacao.pedido.editar`.

## Alternativas consideradas

- Botão por linha — rejeitado; pedido pediu o mesmo padrão do select de adicionar.
- Permitir remover após finalizar captação — rejeitado; pedido e quantidades já seguem no pipeline.

## Consequências

- [PLAN-0128](../plans/PLAN-0128-matriz-remover-loja-captacao.md).
- Colunas de frutas permanecem se outra loja ainda as usa (união de colunas da matriz).
