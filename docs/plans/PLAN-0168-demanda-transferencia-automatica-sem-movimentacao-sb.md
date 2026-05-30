# PLAN-0168: Demanda automática de transferência — sem movimentação no SB

**ADR:** [ADR-0168](../decisions/ADR-0168-demanda-transferencia-automatica-sem-movimentacao-sb.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Garantir que demandas automáticas de transferência da rota não gerem movimentações SB ao anexar NF, e permitir reverter registros criados indevidamente.

## Pré-requisitos

- ADR-0157, ADR-0160 e ADR-0162 lidas.

## Passos

1. **Domínio** — `deveCriarMovimentacaoSb()` em `CaptacaoDemandaTransferenciaRotaService`; pular `criarMovimentacaoTransferencia` quando `id_captacao_rota` preenchido.
2. **Correção** — comando `captacao:reverter-movimentacao-transferencia-automatica {demanda}`.
3. **Testes** — ajustar `CaptacaoMatrizTest` e adicionar caso que NF concluída não cria `movimentacoes` de transferência.
4. **Dados** — executar comando para demanda #45 (ambiente local).

## Critério de conclusão

- Testes de captação/matriz verdes.
- Demanda automática com NF concluída: `transferencia_origem_id` nulo e sem movimentações ativas de transferência vinculadas.

## Riscos

- Demandas já concluídas com movimentação SB — mitigar com comando de reversão.
