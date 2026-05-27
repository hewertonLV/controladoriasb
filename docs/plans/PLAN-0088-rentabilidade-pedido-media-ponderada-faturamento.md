# PLAN-0088: Rentabilidade do pedido — média ponderada por faturamento

**ADR:** [ADR-0088](../decisions/ADR-0088-rentabilidade-pedido-media-ponderada-faturamento.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Calcular Rent.% total do pedido como média ponderada pelo faturamento de cada linha, excluindo itens sem custo de referência.

## Pré-requisitos

- ADR-0073 (margem percentual por linha)
- `CaptacaoPrecificacaoService::margemPercentual()`

## Passos

1. **Refatorar `rentabilidadePedido()`** — acumular `Σ(pct × fat)` e `Σ(fat)` só em linhas elegíveis.
2. **Testes unitários** — dois itens com pesos distintos (≠ média simples); linha sem custo não altera a média.
3. **Verificar consumidores** — pedido por loja e `PedidoCaptacaoEstadoService` (sem mudança de contrato).

## Critério de conclusão

- Testes `CaptacaoPrecificacaoServiceTest` e `CaptacaoPedidoPorLojaTest` verdes.
- Linha sem custo: coluna «—» e total coerente com linhas elegíveis.

## Riscos

- Pedido 100% sem custo → total Rent.% ausente — mitigação: exibir «—» na UI (já existente).
