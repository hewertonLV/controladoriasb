# PLAN-0143: Saída estoque físico padrão por unidade de negócio

**ADR:** [ADR-0143](../decisions/ADR-0143-cliente-saida-fisico-unidade-negocio.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Select no cliente com galpão do faturamento, galpões da rede e HUBs; persistir FK e resolver na captação.

## Passos

1. Migration FK + migração de dados do enum.
2. `ClienteSaidaFisicoPadraoOpcoesService` + ajuste `SaidaEstoqueFisicoCaptacaoService`.
3. Formulário cliente (select agrupado + JS ao trocar faturamento).
4. Validação e testes.

## Critério de conclusão

Cadastro lista opções corretas; captação pré-seleciona conforme FK; testes verdes.

## Riscos

- Cliente com preferência fora do lote — fallback para unidade equivalente do lote.
