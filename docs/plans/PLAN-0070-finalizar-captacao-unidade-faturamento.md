# PLAN-0070: Finalizar captação da unidade de faturamento

**ADR:** [ADR-0070](../decisions/ADR-0070-finalizar-captacao-unidade-faturamento.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Implementar fechamento da captação por `(data, unidade de faturamento)` pelo Atanásio, consolidando romaneios de todos os galpões e liberando a fila do Lucas.

## Pré-requisitos

- Lotes por galpão do [PLAN-0066](PLAN-0066-captacao-pedido-romaneios-fechamento-diario.md).
- Permissão vinculada à unidade de faturamento do usuário.

## Passos

1. Migration `captacao_faturamento_dias` (data, id_unidade_faturamento, status, user_id, finalized_at).
2. Serviço `FinalizarCaptacaoFaturamentoService` — valida rotas; loop galpões; consolida romaneios; atualiza status.
3. Bloquear API/matriz: novos pedidos e alteração de qtd/rota após finalizar (preço liberado até ADR-0067).
4. Tela admin — botão “Finalizar captação” na visão do faturamento/dia; checklist de galpões pendentes.
5. Fila Lucas — filtrar `AGUARDANDO_TRANSFERENCIA_CIGAN` somente se faturamento/dia finalizado.
6. Testes — não liberar Lucas antes; consolidar N galpões; bloqueio sem rota.

## Critério de conclusão

- Atanásio finaliza Barbalha do dia → todos os galpões consolidados → Lucas vê os lotes.
- Antes de finalizar, Lucas não acessa “Iniciar transferência”.

## Riscos

- Galpão sem pedidos no dia — ignorar na consolidação ou exigir confirmação explícita.
