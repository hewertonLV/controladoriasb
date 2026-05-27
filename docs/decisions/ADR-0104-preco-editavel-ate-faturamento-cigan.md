# ADR-0104: Preço editável até Faturamento Cigan iniciado

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Pipeline Lucas/Jefferson ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md))

## Contexto

A tabela de travas em [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md) previa bloqueio de preço após **Concluir etapa de frete** (`TRANSFERENCIA_FINALIZADA`). A operação precisa ajustar preços de venda na matriz durante todo o pipeline de transferência, até Jefferson **Iniciar faturamento** no Cigan.

## Decisão

- **`permiteEdicaoPreco()`** = verdadeiro em todos os status **exceto** `FATURAMENTO_CIGAN_INICIADO` e `VENDAS_FINALIZADAS`.
- Quantidades permanecem travadas após sair de `CAPTACAO_EM_ANDAMENTO` (regra existente).
- Matriz: campo preço editável mesmo com loja concluída, enquanto `permiteEdicaoPreco()`.
- API `PATCH /celula`: após captação, aceita apenas alteração de `preco_venda` (rejeita mudança de quantidade).

## Alternativas consideradas

- Travar preço em `TRANSFERENCIA_FINALIZADA` (ADR-0067 original) — rejeitado pela operação.
- Permitir preço após `FATURAMENTO_CIGAN_INICIADO` — rejeitado; arquivo de vendas já gerado.

## Consequências

- Atualiza linha de preço na tabela de travas de [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md).
- [PLAN-0104](../plans/PLAN-0104-preco-editavel-ate-faturamento-cigan.md).
