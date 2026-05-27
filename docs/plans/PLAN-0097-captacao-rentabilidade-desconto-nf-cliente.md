# PLAN-0097: Rentabilidade na captação considera desconto NF do cliente

**ADR:** [ADR-0097](../decisions/ADR-0097-captacao-rentabilidade-desconto-nf-cliente.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Calcular rentabilidade de pedidos na captação usando preço efetivo após `desconto_nf` do cliente.

## Pré-requisitos

- Campo `clientes.desconto_nf` existente.
- Regra de desconto em vendas (`VendaMovimentacaoService`).

## Passos

1. **Helper de preço efetivo** — `precoVendaEfetivo()` e parâmetro opcional em margem/rentabilidade.
2. **Controller e estado loja** — passar `desconto_nf` do cliente aos métodos de rentabilidade.
3. **UI** — badge e nota de faturamento líquido quando houver desconto.
4. **Testes** — unitários e feature com cliente 10% de desconto.

## Critério de conclusão

- Rentabilidade item/pedido reflete desconto NF; testes verdes.

## Riscos

- Desconto alterado após pedido concluído recalcula rentabilidade na consulta — mitigação: mesmo comportamento da venda; preço bruto permanece no item.
