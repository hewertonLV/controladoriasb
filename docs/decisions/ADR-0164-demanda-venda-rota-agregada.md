# ADR-0164: Demanda de venda agregada por rota (romaneio)

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Demandas de venda na conclusão da rota ([ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md), [ADR-0162](ADR-0162-demanda-transferencia-rota-agregada.md))

## Contexto

A operação trata a venda da rota como visão de **romaneio por rota**: várias lojas com suas frutas no mesmo carregamento. Criar uma demanda/nota por loja fragmenta cards, efetivação e download CIGAM. O arquivo CIGAM de vendas já é montado por loja dentro do lote; na rota, o usuário precisa da mesma visão concentrada antes de efetivar.

## Decisão

- **Uma demanda de venda** por `(lote, rota)` na conclusão, cabeçalho com `id_pedido` nulo.
- **Linhas da demanda:** `(id_pedido, id_fruta, qtd_um, preco_venda)` — uma linha por loja × fruta, ordenação de exibição pela `ordem_carregamento` do pedido (romaneio).
- **Notas de venda SB:** criadas na **efetivação manual** (uma `VendaNota` por loja), não na conclusão da rota.
- **CIGAM:** download na demanda filtra pedidos/linhas da rota (`CiganEdiNfVendaGerador::gerarPorDemanda`), mantendo blocos N/I por loja como no lote.
- **Efetivação:** ação única na demanda da rota; valida estoque por loja na unidade de saída física efetiva; desacoplada da transferência ([ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md)).
- **Migration:** consolida registros legados `VENDA_NOTA` com `id_pedido` preenchido em um cabeçalho por rota + linhas.

## Alternativas consideradas

- **Manter uma demanda por loja (ADR-0159)** — rejeitada; operação pede romaneio por rota e card único no módulo Vendas.
- **Agrupar só na UI mantendo N registros** — rejeitada; duplicaria efetivação e CIGAM por loja.

## Consequências

- [PLAN-0164](../plans/PLAN-0164-demanda-venda-rota-agregada.md).
- Atualiza [ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md): efetivação passa a ser por demanda da rota, não por loja isolada.
- `captacao_lote_movimentacao_linhas` ganha `id_pedido` e `preco_venda`; unique por `(demanda, fruta, pedido)`.
