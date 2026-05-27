# PLAN-0122: Exclusão de captação em andamento na listagem

**ADR:** [ADR-0122](../decisions/ADR-0122-exclusao-captacao-em-andamento.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Permitir excluir da listagem um lote em `CAPTACAO_EM_ANDAMENTO`, com confirmação e limpeza de vínculos.

## Pré-requisitos

- ADR-0122 aceita.
- Permissão `captacao.lote.excluir` no enum e seeder.

## Passos

1. **Serviço** — `ExcluirCaptacaoLoteService` com transação e remoção de filhos/arquivos.
2. **Action + rota** — `ExcluirCaptacaoLoteAction`, `DELETE` em `CaptacaoLoteController::destroy`.
3. **UI** — botão na listagem com `data-confirm`, visível só em andamento.
4. **Testes** — sucesso com pedidos; bloqueio fora de andamento; botão ausente em outros status.

## Critério de conclusão

Testes de `CaptacaoLoteTest` passam; exclusão remove lote (soft) e pedidos; botão condicional na listagem.

## Riscos

- Movimentações órfãs — mitigado checando `captacao_lote_movimentacoes` antes de excluir.
