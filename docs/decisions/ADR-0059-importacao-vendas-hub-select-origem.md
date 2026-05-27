# ADR-0059: Importação de vendas — HUB no select de origem

**Data:** 2026-05-22
**Status:** Substituída por [ADR-0060](ADR-0060-venda-origem-comercial-saida-fisica-hub.md)
**Contexto:** Importação de vendas — override de origem (ADR-0053)

## Contexto

A prévia permitia alterar a origem por NF, mas o select listava apenas unidades com estoque **exceto HUB**. A operação precisa escolher HUB como origem física, como na venda manual.

## Decisão

- `empresas_origem` na API de resultado inclui unidades HUB (`is_hub = true`) com `possui_estoque`.
- Cada opção traz flag `is_hub` para a UI.
- Quando a origem selecionada for HUB, exibir select de **unidade de faturamento** (não-HUB) por grupo NF + cliente.
- Confirmação aceita `id_unidade_negocio_faturamento_por_row` e repassa a `registrarVenda`.
- Linhas cuja origem vem da planilha como HUB continuam em erro no preview (ADR-0048); o override na prévia é o caminho para origem HUB.

## Alternativas consideradas

- **Manter HUB fora do select** — rejeitado; pedido operacional.
- **Permitir HUB sem faturamento na importação** — rejeitado; mesma regra da venda manual.

## Consequências

- ADR-0053 atualizado: select inclui HUB + faturamento condicional.
- Confirmação valida faturamento obrigatório quando origem efetiva for HUB.
