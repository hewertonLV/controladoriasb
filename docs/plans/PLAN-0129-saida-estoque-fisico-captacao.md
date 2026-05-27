# PLAN-0129: Saída estoque físico após transferência Cigam

**ADR:** [ADR-0129](../decisions/ADR-0129-saida-estoque-fisico-captacao.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Inserir status e aba de saída física por loja; adiar transferências gerenciais ao «Concluir»; debitar venda do estoque escolhido.

## Pré-requisitos

- Lote em `TRANSFERENCIA_CIGAN_INICIADA` com HUB de origem definido.

## Passos

1. Migration `pedidos.id_unidade_negocio_saida_venda` + enum/status/pipeline.
2. NF upload → `SAIDA_ESTOQUE_FISICO` sem transferências.
3. Aba Romaneio 1 + radios galpão/HUB + PATCH AJAX.
4. Action «Concluir saída estoque físico» + transferências.
5. `GerarVendasCaptacaoLoteService` usa saída por pedido.
6. Testes pipeline e saída física.

## Critério de conclusão

Fluxo completo testado: NF → aba saída → concluir → frete → vendas com estoque correto por loja.

## Riscos

- Lote sem HUB definido na aba — mitigação: desabilitar opção HUB até hub_origem salvo.
