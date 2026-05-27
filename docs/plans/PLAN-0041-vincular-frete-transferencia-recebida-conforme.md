# PLAN-0041: Vincular frete em transferência recebida conforme

**ADR:** [ADR-0041](../decisions/ADR-0041-vincular-frete-transferencia-recebida-conforme.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir vincular/alterar/remover frete em transferência pendente ou recebida conforme, com recálculo de rateio e replay de estoque no destino.

## Pré-requisitos

- ADR-0003 (rateio central de frete) em vigor.
- Permissão `movimentacoes.transferencias.editar` no seeder.

## Passos

1. **Serviço** — `TransferenciaMovimentacaoService::vincularFrete`.
2. **HTTP** — request, action, rota, controller.
3. **UI** — formulário na `show` da transferência.
4. **Testes** — vincular frete após recebimento conforme altera custo de entrada.

## Critério de conclusão

- POST `vincular-frete` atualiza frete e recalcula; estoque destino reflete novo preço de entrada quando conforme.
- Testes passam.

## Riscos

- Replay pesado em unidades com muitos eventos — mitigar com transação única e lock já existente no replay.
