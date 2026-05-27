# PLAN-0121: Captação complementar com dia finalizado

**ADR:** [ADR-0121](../decisions/ADR-0121-captacao-complementar-com-dia-faturamento-finalizado.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Permitir novo lote complementar com `captacao_faturamento_dias` finalizado.

## Passos

1. Remover bloqueio por dia de faturamento finalizado e por status do lote anterior.
2. Manter só bloqueio/recuperação quando existe `CAPTACAO_EM_ANDAMENTO`.
3. Mensagem no controller e testes feature.

## Critério de conclusão

Qualquer status exceto `CAPTACAO_EM_ANDAMENTO` permite novo lote; dia de faturamento finalizado não bloqueia.
