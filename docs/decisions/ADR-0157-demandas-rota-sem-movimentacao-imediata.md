# ADR-0157: Demandas na conclusão da rota — sem movimentação imediata

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Captação — conclusão de rota na aba Por rota; revisão de [ADR-0154](ADR-0154-transferencia-venda-pendente-conclusao-rota.md)

## Contexto

Na conclusão da rota, o operador finaliza o que foi pedido (quantidades, saída física, ordem). A movimentação de estoque no SB Controladoria não deve ocorrer nesse instante — apenas o registro das **demandas** de transferência e venda, efetivadas depois por interação do usuário e pelos status de cada demanda.

## Decisão

- **Conclusão da rota:** após validações existentes (motorista, veículo, ordem, captação concluída), o sistema **monta demandas** em `captacao_lote_movimentacoes` e vínculos de venda (`VendaNota` / equivalente), **sem** criar movimentações de estoque, **sem** debitar origem e **sem** validar saldo disponível.
- **Transferência:** movimentação SB somente quando a demanda de transferência passar a `CONCLUIDO` (ver [ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md)).
- **Venda:** movimentação SB somente quando a demanda de venda passar a `CONCLUIDO`, por ação explícita do usuário (ver [ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md)).
- **Demanda automática de transferência:** criada na conclusão da rota **somente** quando a saída física efetiva do pedido ≠ unidade de faturamento do lote (`id_unidade_negocio_galpao` / galpão operacional de faturamento), **uma demanda por `(lote, rota, unidade origem)`** com linhas `(fruta, qtd_um)` agregadas — registro em status `ABERTO`, **sem** `TransferenciaMovimentacaoService` na conclusão. Ver [ADR-0162](ADR-0162-demanda-transferencia-rota-agregada.md).
- **Sem demanda automática:** pedidos cuja saída física efetiva = galpão de faturamento do lote **não** geram demanda de transferência, **mesmo** com estoque insuficiente na conclusão.
- **Idempotência:** reconclusão da mesma rota não duplica demandas já existentes para o mesmo par lógico; reconclusão após reabertura recria demandas conforme [ADR-0155](ADR-0155-status-demanda-rota-reabrir.md).

## Alternativas consideradas

- **Movimentar na conclusão (ADR-0154 original)** — rejeitada; bloqueava rotas válidas por falta de estoque antes da operação fiscal/logística.
- **Validar estoque na conclusão** — rejeitada neste momento; estoque é exigido ao **iniciar** demanda de transferência ou ao **efetivar** venda.

## Consequências

- [PLAN-0157](../plans/PLAN-0157-demandas-rota-sem-movimentacao-imediata.md).
- `EfetivarDemandasMovimentacaoRotaCaptacaoService` passa a **registrar demandas**, não chamar `criarTransferenciaAguardandoRecebimento` nem gerar movimentações de venda imediatas.
- Complementa [ADR-0135](ADR-0135-venda-hub-co-faturamento-embutido-custo-saida.md) na **venda** (CO do faturamento na saída), não na conclusão da rota.
