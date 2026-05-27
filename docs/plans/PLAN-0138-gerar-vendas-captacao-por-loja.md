# PLAN-0138: Gerar vendas da captação por loja

**ADR:** [ADR-0138](../decisions/ADR-0138-gerar-vendas-captacao-por-loja.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Gerar movimentações de venda para todas as lojas com quantidade, mesmo após execução parcial anterior.

## Passos

1. Ajustar `GerarVendasCaptacaoLoteService` (idempotência por pedido + resumo).
2. Rota `sincronizar-vendas-pendentes` e bloco na aba Arquivo Cigam Venda.
3. Teste de segunda loja após primeira geração.

## Critério de conclusão

- Teste `test_gerar_vendas_gera_lojas_pendentes_apos_primeira_execucao` verde.
- Resumo na matriz mostra status por loja.

## Riscos

- NF existente sem todas as movimentações — mitigação: resumo destaca pendência; sincronização só cria lojas sem vínculo completo.
