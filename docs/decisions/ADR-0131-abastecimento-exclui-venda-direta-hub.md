# ADR-0131: Abastecimento galpão exclui loja com saída física no HUB

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Saída estoque físico captação ([ADR-0129](ADR-0129-saida-estoque-fisico-captacao.md))

## Contexto

Loja com saída física marcada no HUB vende direto desse estoque (`id_unidade_negocio_saida_venda` = HUB). Incluir essa demanda no romaneio «a receber» gerava transferência HUB→galpão desnecessária; na finalização a venda já debitaria o HUB.

## Decisão

- No `RomaneioAbastecimentoService`, a **demanda para abastecer o galpão** soma apenas pedidos cuja saída física **não** é o HUB de origem do lote (`id_unidade_negocio_saida_venda` nulo ou galpão).
- `a receber` (transferência, arquivo Cigan, validação de estoque para transferência) usa só essa demanda.
- **Necessidade total no HUB** por fruta = `a receber` (transferência) + demanda das lojas com saída no HUB (venda direta). A validação de estoque no HUB ([ADR-0130](ADR-0130-nf-transferencia-validar-estoque-hub.md)) usa essa soma.

## Alternativas consideradas

- Transferir tudo e devolver na venda — rejeitado; distorce estoque galpão/HUB.
- Escolha só na venda, sem alterar transferência — rejeitado; pedido explícito da operação.

## Consequências

- Concluir saída estoque físico não move estoque ao galpão para lojas HUB.
- Arquivo Cigan de transferência reflete apenas o que vai ao galpão.
- [PLAN-0131](../plans/PLAN-0131-abastecimento-exclui-venda-direta-hub.md).
