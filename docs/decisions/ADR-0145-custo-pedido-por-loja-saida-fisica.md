# ADR-0145: Custo na captação por loja conforme saída física

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Pedidos por loja com seleção de saída física ([ADR-0144](ADR-0144-saida-fisica-override-pedido-por-loja.md))

## Contexto

O custo exibido e gravado em `pedidos_itens.custo_referencia` usava sempre o PM do galpão do lote. Com a escolha de saída (galpão ou HUB), a referência deve refletir o estoque da unidade de onde a venda sairá e, na saída HUB, o CO da unidade de faturamento — mesma regra da venda ([ADR-0077](ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md), `VendaCustoOperacionalHub`).

## Decisão

- **PM:** estoque ativo (`preco_medio_um` ou derivado de `preco_medio_kg`) na **unidade de saída física** efetiva do pedido/lote.
- **CO adicional:** somente se a unidade de saída for HUB — CO vigente (R$/kg) da **unidade de faturamento do lote**, convertido para a UM da fruta (`co_kg × kg_por_um`).
- Ao alterar a saída física na tela, recalcular `custo_referencia` dos itens do pedido e atualizar a coluna Custo via AJAX.
- `CaptacaoPrecificacaoService::custoReferenciaPorUmNaSaidaFisica` centraliza o cálculo; `PedidoService` usa na gravação dos itens.

## Alternativas consideradas

- Manter PM só do galpão do lote — rejeitado; diverge da saída escolhida.
- CO da praça do cliente (ADR-0077 margem venda não-HUB embed) — rejeitado aqui; alinhar com venda HUB que embute CO do **faturamento**.

## Consequências

- [PLAN-0145](../plans/PLAN-0145-custo-pedido-por-loja-saida-fisica.md).
- Sem estoque na unidade de saída: custo indisponível (`s/ est.`).
