# ADR-0155: Status de demanda e reabertura de rota

**Data:** 2026-05-28
**Status:** Atualizado — gatilhos de status revisados em [ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md) e [ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md)
**Contexto:** Captação — demandas de transferência e venda na conclusão da rota

## Contexto

Demandas geradas ao concluir a rota precisam de ciclo de vida explícito e regras para reabrir a rota sem exclusão manual avulsa.

## Decisão

- **Status da demanda** (transferência e venda), em `captacao_lote_movimentacoes.status_demanda`: `ABERTO` → `INICIADO` → `CONCLUIDO`.
- **Aberto:** criada na conclusão da rota (**sem** movimentação SB).
- **Iniciado:** transferência — usuário inicia após validar estoque na origem ([ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md)); venda — usuário inicia efetivação ([ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md)).
- **Concluido:** transferência — após NF anexada, movimentação SB gerada; venda — movimentações de saída geradas.
- **Reabrir rota:** permitido somente se **todas** as demandas de transferência e venda da rota estiverem `ABERTO`. Ao reabrir, o sistema **remove** automaticamente as demandas (cancela transferência pendente + exclui vínculos/nota de venda aberta) — **sem botão** de exclusão em outro cenário.
- **Reconclusão:** gera novas demandas com quantidades atualizadas (sem reutilizar registros removidos).

## Alternativas consideradas

- **Exclusão manual na UI** — rejeitada; só via reabertura de rota.
- **Reabrir com demanda concluída** — rejeitada; exige estorno complexo de venda/transferência finalizada.

## Consequências

- [PLAN-0155](../plans/PLAN-0155-status-demanda-rota-reabrir.md).
- Complementa [ADR-0154](ADR-0154-transferencia-venda-pendente-conclusao-rota.md) e [ADR-0152](ADR-0152-rota-concluida-matriz-por-rota.md).
