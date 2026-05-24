# ADR-0073: Captação no app — custo, preço de venda por UM e margem

**Data:** 2026-05-23
**Status:** Aceito (atualizado — ver [ADR-0077](ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md))
**Contexto:** App de captação ([ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md)); frutas com UM ([ADR-0042](ADR-0042-unidade-medicao-fruta-pct-pacote-kg.md), [ADR-0044](ADR-0044-unidade-medicao-fruta-bdj-bandeja.md))

## Contexto

Na captação pelo **aplicativo**, o captador precisa ver o **custo de referência** da fruta, informar o **preço de venda na unidade de medição** (KG, CX, PCT, etc.) e visualizar a **margem** antes de confirmar o item. A matriz web deve exibir os mesmos campos ao editar a célula (preço e margem; custo como referência).

## Decisão

### Unidade de medição

- Toda exibição e entrada de preço usa a **`unidade_medicao`** da fruta cadastrada (não forçar KG na UI se a fruta for CX/PCT).
- API expõe `unidade_medicao`, `kg_por_unidade_medicao` e rótulo amigável para o app.

### Custo de referência (somente leitura no app)

- **Única fonte:** preço médio gerencial do estoque do **galpão** para a fruta (saldo > 0), na UM da fruta ([ADR-0077](ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md)).
- O PM **já inclui** ICMS (compra), frete (transferências) e demais custos já lançados — não acrescentar CO nem frete na margem da captação.
- Sem saldo no galpão: `null` e aviso “sem estoque — custo indisponível”.
- Gravar **snapshot** em `pedido_item.custo_referencia_por_um` no lançamento (pode divergir do PM atual se houver transferência antes da venda).

### Preço de venda

- Campo obrigatório **`preco_venda_por_um`** (R$ / mesma UM da fruta).
- Validação: numérico ≥ 0; casas decimais conforme UM (2 ou 3 para PCT — ADR-0042).

### Margem (calculada)

Quando `custo_referencia_por_um` estiver disponível:

- `margem_por_um = preco_venda_por_um - custo_referencia_por_um`
- `margem_percentual = (margem_por_um / preco_venda_por_um) × 100` se preço > 0
- Com quantidade informada: `margem_total = margem_por_um × quantidade` (na UM da fruta)

Exibir no app: custo (PM estoque), preço, margem R$/UM, % e total. CO explícito só na venda com saída HUB ([ADR-0077](ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md)).

### API

- `GET .../captacao/preco-referencia?cliente=&fruta=&galpao=` → custo, UM, última venda usada (metadado).
- Create/update pedido item persiste preço, custo snapshot e margens calculadas no servidor (não confiar só no cliente).

### Web (matriz)

- Célula exibe/edita quantidade e **preço por UM**; mostra custo referência e margem recalculada no blur (mesma regra).

## Alternativas consideradas

- **Só preço sem custo/margem** — rejeitado; requisito operacional explícito.
- **Forçar preço sempre em R$/kg** — rejeitado; fruta vende em CX/PCT/etc.
- **Margem com CO e frete na captação** — rejeitado; já embutidos no PM quando em estoque ([ADR-0077](ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md)).
- **Fallback por última venda** — rejeitado; único modelo = PM do estoque.

## Consequências

- [PLAN-0073](../plans/PLAN-0073-captacao-app-custo-preco-margem-um.md); integrar em [PLAN-0068](PLAN-0068-api-pedidos-painel-tempo-real.md).
- Campos em `pedido_itens`; endpoint de referência; testes de prioridade do custo.
- App exibe alerta visual se margem negativa.
