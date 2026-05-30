# PLAN-0165: Matriz sem timeline e fechamento do lote

**ADR:** [ADR-0165](../decisions/ADR-0165-captacao-loja-apos-rotas-concluidas.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Remover timeline obsoleta da matriz e fechar o lote em `CAPTACAO_CONCLUIDA` após rotas e lojas; regras loja↔rota permanecem na ADR-0152.

## Pré-requisitos

- ADR-0152 (concluir/reabrir rota e loja).

## Passos

1. **Remover timeline** — matriz sem `_lote-timeline-status`.
2. **Domínio** — `pendenciasParaConcluirRota` exige `captacao_concluida` por loja; lote exige rotas concluídas.
3. **Testes** — helper `prepararLojaComRota`: captação da loja antes da rota; suíte `CaptacaoMatrizTest` verde.

## Critério de conclusão

- ADR-0152 é a fonte única para loja↔rota; ADR-0165/0166 sem texto conflitante; testes verdes.

## Riscos

- Confusão entre ADR-0165 antiga e ADR-0152 — mitigado na revisão da ADR-0165.
