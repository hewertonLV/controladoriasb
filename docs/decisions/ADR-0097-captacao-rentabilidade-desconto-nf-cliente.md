# ADR-0097: Rentabilidade na captação considera desconto NF do cliente

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Pedido por loja (`/admin/captacao/lotes/{lote}/pedidos-por-loja/{cliente}`)

## Contexto

O preço captado na matriz/pedido é o valor bruto da NF. Clientes possuem `desconto_nf` no cadastro. Na venda, o valor efetivo já aplica esse desconto (`valorBruto × (1 − desconto/100)`). A rentabilidade na captação usava o preço bruto, superestimando margem % e faturamento líquido.

## Decisão

`CaptacaoPrecificacaoService` aplica o `desconto_nf` do cliente ao calcular margem por item, margem % e rentabilidade agregada do pedido. O preço de entrada permanece o bruto gravado no item; o desconto é parâmetro de cálculo (mesma regra de `VendaMovimentacaoService::valorRealVendaComDesconto`).

## Alternativas consideradas

- Manter preço bruto na rentabilidade — rejeitada: diverge da receita real e do módulo de vendas.
- Persistir preço líquido no `pedido_item` — rejeitada: duplicaria dado derivável e quebraria snapshot histórico se o desconto mudar depois.

## Consequências

- Cards de loja e tela de detalhe exibem rentabilidade alinhada ao desconto vigente do cliente no momento da consulta.
- Faturamento total na rentabilidade passa a ser líquido (após desconto NF).
- UI indica badge "Desc. NF X%" quando o cliente possui desconto.
