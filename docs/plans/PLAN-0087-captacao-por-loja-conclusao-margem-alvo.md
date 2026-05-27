# PLAN-0087: Captação por loja, conclusão e margem alvo

**ADR:** [ADR-0087](../decisions/ADR-0087-captacao-por-loja-conclusao-margem-alvo.md)
**Data:** 2026-05-25
**Status:** Concluído

## Objetivo

Telas carteira → loja → detalhe; conclusão por loja na matriz e por loja; gate na finalização; margem alvo no cliente.

## Passos

1. Migration `captacao_concluida`, `percentual_margem_alvo`.
2. Services precificação, estado card, conclusão pedido.
3. Controller + views pedidos-por-loja.
4. Matriz coluna concluir; FinalizarCaptacao validação.
5. Formulário cliente; menu; testes.

## Critério de conclusão

Testes feature verdes; fluxo manual carteira → loja → concluir → finalizar bloqueado/liberado.
