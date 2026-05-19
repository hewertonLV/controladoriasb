# PLAN-0014: Importação de ICMS separada e quatro tipos com UM própria

**ADR:** [ADR-0014](../decisions/ADR-0014-importacao-icms-separada-e-quatro-tipos.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Frutas importam só dados mestres; ICMS tem cadastro completo (4 tipos + UM) e importação Excel dedicada.

## Passos

1. Migration expandir `fruta_icms` e criar `fruta_icms_importacoes`.
2. Atualizar model, sync, cálculo, requests e `_icms_estados`.
3. Remover ICMS da importação de frutas.
4. Implementar importação ICMS (processor, job, controller, view, rotas, permissões).
5. Testes e links na listagem de frutas.

## Critério de conclusão

Planilha de frutas A–D; planilha ICMS A–J; formulário com 4 pares valor+UM por estado; testes de importação passam.

## Riscos

- Planilhas legadas de frutas com ICMS — documentar novo layout na tela de importação.
