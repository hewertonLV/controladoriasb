# ADR-0121: Nova captação no mesmo dia × carteira

**Data:** 2026-05-26
**Status:** Aceito (atualizado)
**Contexto:** Abertura de lotes ([ADR-0120](ADR-0120-multiplos-lotes-captacao-mesmo-dia-carteira.md))

## Contexto

A operação precisa abrir mais de um lote no mesmo dia e carteira (captação complementar). O bloqueio por dia de faturamento finalizado ou por exigir status «Transferência finalizada» no lote anterior impedia casos válidos.

## Decisão

- **Único bloqueio** para criar outro lote na mesma `(data_referencia, id_captacao_carteira)`: já existir lote com status **`CAPTACAO_EM_ANDAMENTO`** (nesse caso recupera o lote aberto).
- **Qualquer outro status** do lote anterior (aguardando transferência, transferência finalizada, faturamento, etc.) **permite** criar novo lote em `CAPTACAO_EM_ANDAMENTO`.
- **Dia de faturamento finalizado** (`captacao_faturamento_dias`) **não** impede abrir novo lote na carteira.

## Alternativas consideradas

- Exigir `TRANSFERENCIA_FINALIZADA` no lote anterior — rejeitado; operação precisa flexibilidade.
- Bloquear por dia de faturamento finalizado — rejeitado; conflita com complementar após Atanásio finalizar o dia.

## Consequências

- [PLAN-0121](../plans/PLAN-0121-captacao-complementar-com-dia-faturamento-finalizado.md).
- Alinha com [ADR-0120](ADR-0120-multiplos-lotes-captacao-mesmo-dia-carteira.md).
- `sincronizarStatusComFaturamentoFinalizado` ([ADR-0102](ADR-0102-reconciliar-lote-faturamento-finalizado.md)) **não** avança lote complementar: permanece em `CAPTACAO_EM_ANDAMENTO` mesmo com dia de faturamento finalizado.
