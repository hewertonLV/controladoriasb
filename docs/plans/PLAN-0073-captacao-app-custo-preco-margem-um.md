# PLAN-0073: Captação — custo, preço por UM e margem (app + API)

**ADR:** [ADR-0073](../decisions/ADR-0073-captacao-app-custo-preco-margem-um.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Expor custo de referência, receber preço de venda por UM e calcular/exibir margem no app e na matriz web.

## Pré-requisitos

- `Pedido` / `pedido_itens` (PLAN-0066).
- Estoque/PM por galpão; vendas históricas.

## Passos

1. Migration — `preco_venda_por_um`, `custo_referencia_por_um`, `margem_por_um`, `margem_percentual`, `margem_total`, `custo_referencia_origem` (enum metadado).
2. `ResolverCustoReferenciaCaptacaoService` — regra de prioridade ADR-0073.
3. `GET captacao/preco-referencia` — JSON para app ao selecionar fruta.
4. `CalcularMargemPedidoItemService` — no create/update; recalcula se preço mudar.
5. API pedido item — validação UM/decimais; retorna bloco `precificacao` na resposta.
6. Matriz web — exibir custo/margem na célula ou tooltip; recalcular no blur do preço.
7. Testes — PM galpão; fallback última venda; margem negativa; PCT 3 decimais.

## Critério de conclusão

- App mostra custo (leitura), aceita preço/UM e exibe margem antes de salvar.
- Snapshot de custo preservado no item para histórico ([ADR-0069](ADR-0069-pedido-historico-alteracoes.md)).

## Ordem

Junto ao PLAN-0068 após modelo `pedido_itens`.
