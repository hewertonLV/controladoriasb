# PLAN-0061: Realocação HUB — sempre puxar da loja de faturamento

**ADR:** [ADR-0061](../decisions/ADR-0061-realocacao-hub-venda-sempre-loja-faturamento.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Garantir que toda venda com saída física no HUB puxe a quantidade vendida da loja comercial, revertendo o custo médio da transferência HUB→loja.

## Pré-requisitos

- ADR-0060 implementada (`id_unidade_negocio_estoque`, `RealocacaoEstoqueHubVendaService`)

## Passos

1. **RealocacaoEstoqueHubVendaService** — remover early return por saldo HUB; realocar qtd integral da venda; débito na loja ao preço da entrada da transferência.
2. **DevolucaoMovimentacaoService** — CO de retorno baseado em `id_unidade_negocio_estoque` da venda.
3. **Testes** — ajustar cenários HUB (origem comercial + estoque HUB); validar realocação integral e custo médio.

## Critério de conclusão

- Venda HUB sempre consome transferência elegível na loja comercial, mesmo com saldo no HUB.
- Custo médio da loja após realocação reflete remoção ao preço da transferência + replay.
- Suite de testes passando.

## Riscos

- Operação vender do HUB sem transferência prévia — venda rejeitada; exige processo operacional alinhado.
