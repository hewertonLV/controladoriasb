# PLAN-0078: Alertas lojas sem pedido (dia da semana)

**ADR:** [ADR-0078](../decisions/ADR-0078-alertas-lojas-sem-pedido-dia-semana.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Aba “Lojas sem pedido” do módulo Alertas comerciais (ver também [PLAN-0080](PLAN-0080-alertas-fruta-habitual-ausente-romaneio.md)).

## Pré-requisitos

- Módulo pedidos em produção ≥ 4 semanas de dados.

## Passos

1. Query habitual 2/4 semanas por dia da semana.
2. Cruzamento com pedidos do lote do dia (em captação).
3. Módulo Alertas comerciais (rota base) + aba lojas + permissão + filtros faturamento/galpão.
4. Testes com dataset sintético de 4 semanas.

## Critério de conclusão

Terça com histórico: loja que pediu 3 terças e não pediu hoje aparece no alerta.

## Ordem

Fase 2 — após PLAN-0070 e pedidos em uso real.
