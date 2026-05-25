# PLAN-0103: Aba Arquivo Cigan TXT na matriz (transferência iniciada)

**ADR:** [ADR-0103](../decisions/ADR-0103-matriz-aba-arquivo-cigan-txt.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Expor aba na matriz com download de TXT vazio na fase Transferência Cigan iniciada.

## Pré-requisitos

- Pipeline com status `TRANSFERENCIA_CIGAN_INICIADA`.

## Passos

1. **Service** — `conteudoTxtTransferencia()` placeholder vazio.
2. **Rota/controller** — download TXT com permissão Lucas.
3. **Matriz** — aba condicional + link na tela do lote.
4. **Testes** — aba visível, download vazio, 404 fora da fase.

## Critério de conclusão

- Aba aparece só em `TRANSFERENCIA_CIGAN_INICIADA`.
- Download retorna `.txt` vazio.
- Testes passam.

## Riscos

- Layout TXT mudar — mitigado: método isolado no service.
