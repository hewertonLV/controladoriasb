# PLAN-0154: Transferência e venda pendente na conclusão da rota

**ADR:** [ADR-0154](../decisions/ADR-0154-transferencia-venda-pendente-conclusao-rota.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Ao concluir uma rota na matriz, gerar transferências pendentes e vendas aguardando transferência para pedidos cuja saída física difere da unidade de faturamento, com vínculo a captação/rota e conclusão da venda após recebimento.

## Pré-requisitos

- ADR-0152 (conclusão de rota com captação concluída).
- Módulos Transferências e Vendas operacionais.

## Passos

1. **Migration** — `captacao_lote_movimentacoes.id_captacao_rota`, `id_pedido`, `id_transferencia_origem_dependencia`; `vendas_notas.status_conclusao`, `id_transferencia_origem_bloqueio`.
2. **Transferência pendente** — `criarTransferenciaAguardandoRecebimento` e `confirmarRecebimentoConforme` em `TransferenciaMovimentacaoService`.
3. **Demandas rota** — `EfetivarDemandasMovimentacaoRotaCaptacaoService` agrega frutas e cria vínculos.
4. **Venda pendente** — criar `VendaNota` aguardando; `EfetivarVendaPendenteCaptacaoService` ao confirmar transferência.
5. **Integração** — chamar serviço em `concluirRota`; ajustar `GerarVendasCaptacaoLoteService`; rota HTTP confirmar recebimento + botão na view.
6. **Testes** — conclusão de rota gera transferência pendente + venda aguardando; rota sem transferência gera venda concluída; confirmação libera venda pendente.

## Critério de conclusão

- Rota concluída com loja HUB→faturamento gera transferência pendente + venda aguardando, vinculadas ao lote/rota.
- Confirmar recebimento efetiva movimentações de venda na unidade de faturamento.
- Testes PHPUnit verdes.

## Riscos

- Conflito com transferências gerenciais do pipeline — mitigar excluindo pedidos já cobertos por demanda de rota no serviço de saída físico.
