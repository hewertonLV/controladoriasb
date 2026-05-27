# PLAN-0117: Tipo de operação 5152A no EDI Cigan

**ADR:** [ADR-0117](../decisions/ADR-0117-cigan-edi-tipo-operacao-5152a.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Gravar o tipo de operação **5152A** nos registros `N` e `I` do TXT de transferência HUB.

## Passos

1. **Config** — `captacao_cigan_edi.tipo_operacao` default `5152A`.
2. **Gerador** — `tipoOperacaoCigam()` retorna 5 caracteres; `colocarExato` em 20–24 e 372–376.
3. **Testes** — unit + pipeline.
4. **UI** — matriz aba Arquivo Cigan.

## Critério de conclusão

- TXT com `5152A` nas posições de tipo de operação; testes verdes.
