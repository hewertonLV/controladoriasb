# PLAN-0089: Matriz — linha de totais e bloqueio por loja concluída

**ADR:** [ADR-0089](../decisions/ADR-0089-matriz-total-linha-concluida-bloqueada.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Exibir totais por coluna na matriz e impedir edição de linhas com captação concluída.

## Pré-requisitos

- Matriz com botão Concluir/Reabrir por loja (ADR-0087)

## Passos

1. **Serviço** — `totaisPorFruta()`; bloqueio em `upsertCelulaMatriz` se concluída.
2. **View** — linha Total; inputs `disabled`/`readonly` quando concluída.
3. **JS** — não salvar células bloqueadas; `atualizarTotais()` após mudanças.
4. **Testes** — linha Total visível; PATCH celula 422 quando concluída.

## Critério de conclusão

- Testes `CaptacaoMatrizTest` verdes; matriz integração existente verde.

## Riscos

- Polling sobrescrever célula focada — mitigação: já ignorava `activeElement`; incluir `disabled`.
