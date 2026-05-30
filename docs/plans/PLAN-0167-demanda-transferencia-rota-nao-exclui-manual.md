# PLAN-0167: Demanda automática de transferência não exclui manualmente

**ADR:** [ADR-0167](../decisions/ADR-0167-demanda-transferencia-rota-nao-exclui-manual.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Bloquear exclusão manual de demandas automáticas da rota e expor download Cigam no módulo Transferências.

## Passos

1. **Domínio** — `pode_excluir` falso quando `id_captacao_rota`; validação em `excluir()`.
2. **HTTP** — rota `demandas-captacao.cigam` em movimentações.
3. **UI** — remover botão Excluir; label «Baixar arquivo Cigam».
4. **Testes** — bloqueio de exclusão e download Cigam.

## Critério de conclusão

Testes de matriz/captação verdes; demanda automática sem Excluir; Cigam baixável após iniciar.

## Riscos

- Operador confunde com demanda manual — mitigado por aviso no card.
