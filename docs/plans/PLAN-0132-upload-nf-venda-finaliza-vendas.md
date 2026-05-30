# PLAN-0132: Upload NF de venda finaliza vendas no SB

**ADR:** [ADR-0132](../decisions/ADR-0132-upload-nf-venda-finaliza-vendas.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Upload de NF de venda na aba Arquivo Cigam Venda efetiva vendas e avança para Vendas finalizadas.

## Passos

1. Migration colunas NF venda em `captacao_lotes`.
2. Serviços `ArmazenarNfVendaCiganLoteService` + `EfetivarVendasCaptacaoLoteService`.
3. Action, request, rotas, controller.
4. UI `_arquivo-cigan-vendas` + pipeline sem botão finalizar.
5. Testes.

## Critério de conclusão

Upload em faturamento iniciado gera movimentações de venda, grava NF e status `VENDAS_FINALIZADAS`.
