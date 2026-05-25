# ADR-0089: Matriz — linha de totais e bloqueio por loja concluída

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Tela matriz de captação ([ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md), [ADR-0087](ADR-0087-captacao-por-loja-conclusao-margem-alvo.md))

## Contexto

Na matriz agregada, operação precisa ver o **total por fruta** (coluna) e garantir que lojas com captação **concluída** não tenham quantidades alteradas acidentalmente.

## Decisão

- **Linha Total** — última linha de dados (antes da linha «Selecione a loja»), com soma das quantidades de cada coluna (fruta) em todas as lojas da matriz.
- **Loja concluída** (`captacao_concluida = true`) — células da linha ficam **somente leitura** na UI; API de célula rejeita alteração com erro de validação até **Reabrir**.
- Totais recalculados no servidor (render) e no cliente após salvar/polling.

## Alternativas consideradas

- Total só no rodapé fixo fora da tabela — rejeitado; usuário pediu linha na matriz.
- Permitir edição com loja concluída — rejeitado; inconsistente com conclusão ([ADR-0087](ADR-0087-captacao-por-loja-conclusao-margem-alvo.md)).

## Consequências

- `CaptacaoMatrizEstadoService::totaisPorFruta()` e bloqueio em `PedidoService::upsertCelulaMatriz()`.
- [PLAN-0089](../plans/PLAN-0089-matriz-total-linha-concluida-bloqueada.md).
