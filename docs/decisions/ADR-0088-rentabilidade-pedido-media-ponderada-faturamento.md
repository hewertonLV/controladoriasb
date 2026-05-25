# ADR-0088: Rentabilidade do pedido — média ponderada por faturamento

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Tela pedido por loja — card «Último pedido» e agregados de captação ([ADR-0073](ADR-0073-captacao-app-custo-preco-margem-um.md))

## Contexto

O total **Rent.%** do pedido deve refletir a rentabilidade do conjunto de itens, não a média aritmética simples das colunas por linha. Linhas sem `custo_referencia` já exibem «—» na coluna Rent.%, mas o cálculo agregado tratava custo ausente como zero, distorcendo o percentual.

## Decisão

- **Rent.% total** = média ponderada das margens percentuais das linhas, com peso = **faturamento da linha** (`preco_venda × quantidade`).
- Fórmula equivalente: `Σ(margem_%_linha × fat_linha) / Σ(fat_linha)` apenas sobre linhas elegíveis.
- **Linha elegível:** quantidade > 0, preço > 0 e `custo_referencia` informado (mesma regra de `margemPercentual`).
- **Faturamento exibido:** soma de `preco × quantidade` de **todas** as linhas com preço > 0 (inclui itens sem custo).
- **Margem total (R$):** soma de `(preço − custo) × quantidade` só nas linhas elegíveis.
- Se nenhuma linha for elegível: `margem_percentual` e `margem_total` = `null`.

## Alternativas consideradas

- **Média aritmética simples das Rent.%** — rejeitada; linhas de maior faturamento não pesariam corretamente.
- **Incluir linhas sem custo com margem 100%** — rejeitada; inconsistente com a coluna por linha («—»).
- **Excluir do faturamento total linhas sem custo** — rejeitada; o total Fat. deve representar o pedido comercial completo.

## Consequências

- `CaptacaoPrecificacaoService::rentabilidadePedido()` implementa a média ponderada explicitamente.
- Badge e rodapé «Totais» usam o mesmo percentual agregado.
