# PLAN-0030: Replay de estoque ao cancelar venda

**ADR:** [ADR-0030](../decisions/ADR-0030-replay-estoque-cancelamento-venda.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Corrigir restituição de estoque ao cancelar venda administrativamente, inclusive com movimentações posteriores.

## Pré-requisitos

- `ReplayLinhaTempoEstoqueService` e `CancelarVendaMovimentacaoAdminService` existentes

## Passos

1. **Replay parcial** — `reprocessarUnidadeFrutaAposCancelamentoSaida` com baseline condicional
2. **Cancelamento** — remover estorno manual; passar `movimentacao_id` ao replay
3. **Testes** — cenários sem posterior, compra posterior, venda posterior

## Critério de conclusão

- Testes `VendaMovimentacaoTest` de cancelamento passando
- Estoque após cancelamento igual ao esperado nos três cenários

## Riscos

- Devolução vinculada à venda cancelada — validar manualmente em operação
