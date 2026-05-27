# ADR-0122: Exclusão de captação em andamento na listagem

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Listagem de lotes de captação — descarte de captação aberta por engano.

## Contexto

Operadores podem abrir uma captação e precisar removê-la antes de avançar no pipeline (transferência, frete, faturamento). Lotes em outros status representam trabalho já consolidado.

## Decisão

- Exibir botão **Excluir** na listagem **somente** com status `CAPTACAO_EM_ANDAMENTO`.
- Exigir confirmação explícita (modal `data-confirm`) antes do envio.
- Permissão dedicada `captacao.lote.excluir`.
- Ao excluir: remover pedidos (itens e históricos), linhas de frete, exports Cigan, romaneio manual, vínculos de movimentação do lote, arquivos em disco (NF/exports) e **soft delete** do lote.
- Bloquear exclusão se o lote tiver registros em `captacao_lote_movimentacoes` (pipeline já avançou de forma inconsistente com o status).

## Alternativas consideradas

- Reutilizar `captacao.lote.visualizar` — rejeitado: visualizar não implica poder apagar dados.
- Exclusão física (`forceDelete`) — rejeitado: manter soft delete do lote para auditoria, com remoção física dos filhos sem soft delete.

## Consequências

- Após exclusão, pode-se abrir nova captação no mesmo dia × carteira.
- Lotes fora de «em andamento» permanecem sem botão de exclusão.
