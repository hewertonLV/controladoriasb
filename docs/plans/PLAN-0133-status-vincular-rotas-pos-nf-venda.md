# PLAN-0133: Status vincular rotas após NF de venda

**ADR:** [ADR-0133](../decisions/ADR-0133-status-vincular-rotas-pos-nf-venda.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Separar efetivação de vendas (upload NF) da exigência de rotas, com etapa dedicada e auto-avanço quando possível.

## Pré-requisitos

- ADR-0132 implementada (upload NF + movimentações).

## Passos

1. **Enum** — `VincularRotasNosPedidos` em `CaptacaoLoteStatus` + timeline, pipeline, abas.
2. **Serviços** — `AvancarEtapaVinculoRotasCaptacaoLoteService`; ajustar `EfetivarVendasCaptacaoLoteService`.
3. **Action + rota** — `ConcluirVinculoRotasCaptacaoLoteAction`, POST pipeline.
4. **PedidoService** — auto-avanço após `atualizarRotaPedido`; mensagem de validação atualizada.
5. **UI** — botão Concluído no pipeline; textos da aba NF venda.
6. **Testes** — upload sem rota, auto-avanço com rota, concluir manual.

## Critério de conclusão

Upload gera movimentações sem exigir rota; lote em `VINCULAR_ROTAS_NOS_PEDIDOS` até rotas OK; `VENDAS_FINALIZADAS` só após todas vinculadas (auto ou botão).

## Riscos

- Lote com vendas movimentadas e rotas ainda pendentes — mitigação: etapa visível na timeline e aba Rotas.
