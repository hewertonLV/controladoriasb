# ADR-0159: Venda da rota — efetivação manual e desacoplada da transferência

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Demandas de venda na conclusão da rota ([ADR-0157](ADR-0157-demandas-rota-sem-movimentacao-imediata.md))

## Contexto

A venda de cada loja da rota deve ser efetivada pelo usuário, sem vínculo de bloqueio com demandas de transferência. Quando a saída física é o galpão de faturamento e não há estoque, o sistema informa faltas em vez de criar transferência automática.

## Decisão

- **Na conclusão da rota:** uma demanda de venda **por rota** (romaneio), com linhas loja×fruta — ver [ADR-0164](ADR-0164-demanda-venda-rota-agregada.md). Status `ABERTO`, **sem** movimentações de saída e **sem** nota pendente até a efetivação.
- **Efetivação:** sempre por **interação do usuário** (ação única na demanda da rota). Antes de concluir, conferir estoque por loja na unidade de saída física efetiva; **sem** dependência do status da demanda de transferência da mesma rota.
- **Saída HUB (origem ≠ faturamento):** na venda concluída, debitar estoque na **unidade de origem física**; aplicar CO da unidade de faturamento embutido no custo de saída ([ADR-0135](ADR-0135-venda-hub-co-faturamento-embutido-custo-saida.md)); a fruta permanece contabilmente na origem até a venda — a transferência fiscal/operacional para o galpão segue fluxo próprio ([ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md)) **sem** bloquear a venda.
- **Saída = galpão de faturamento:** se estoque insuficiente ao tentar efetivar a venda, **notificar** frutas e quantidades faltantes para aquela rota/loja; **não** criar demanda de transferência automática. O usuário cria demanda **manual** no módulo Transferências ([ADR-0160](ADR-0160-demanda-transferencia-manual-multi-fruta.md)).
- **Status venda:** `ABERTO` → `INICIADO` (ao iniciar efetivação) → `CONCLUIDO` (movimentações geradas). Sem `AGUARDANDO_TRANSFERENCIA` bloqueando venda.

## Alternativas consideradas

- **Venda aguardando transferência (ADR-0154 original)** — rejeitada; operação pediu desacoplamento.
- **Criar transferência automática por falta de estoque no galpão** — rejeitada; substituída por alerta + demanda manual.

## Consequências

- [PLAN-0159](../plans/PLAN-0159-venda-rota-desacoplada-transferencia.md).
- Remover ou desativar `status_conclusao = AGUARDANDO_TRANSFERENCIA` para vendas de rota captação.
- UI de demandas de venda: ação de efetivar + modal/lista de faltas de estoque.
