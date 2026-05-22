# PLAN-0049: Movimentação Entrada de Estoque (produção)

**ADR:** [ADR-0049](../decisions/ADR-0049-entrada-estoque-producao.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Tela e API web para lançar entrada de estoque da produção com preço médio ponderado.

## Passos

1. Categoria id 8 + enum + seeder.
2. `EntradaEstoqueMovimentacaoService` + replay.
3. Controller, rotas, permissões, views.
4. Testes feature.

## Critério de conclusão

- Criar entrada aumenta saldo e recalcula preço médio; cancelamento admin restaura via replay.
