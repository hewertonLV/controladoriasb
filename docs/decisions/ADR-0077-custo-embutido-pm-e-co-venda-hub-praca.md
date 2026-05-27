# ADR-0077: Custo embutido no PM; CO na venda só na saída do HUB pela praça

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Resposta aos itens B e D; refine [ADR-0073](ADR-0073-captacao-app-custo-preco-margem-um.md) e [ADR-0063](ADR-0063-venda-hub-co-unidade-faturamento.md)

## Contexto

Na cadeia de custo, **compra** já incorpora ICMS no preço de entrada; **transferência** incorpora frete (rateio) no preço médio. O **preço médio do estoque** é a referência única de custo na captação. O **custo operacional (CO)** da praça **não** entra de novo no PM do galpão/loja; só na **venda**, e **apenas** quando a saída física é de unidade tipo **HUB**, aplicando o CO da unidade de negócio vinculada à **praça** do cliente final.

## Decisão

### Referência de custo (captação e exibição)

- **Única fonte:** `preco_medio` gerencial do estoque na unidade de saída prevista (galpão do lote ou contexto da linha), convertido para a UM da fruta.
- Esse PM **já reflete** ICMS (compra), frete (transferências) e demais componentes já lançados — **não** somar CO nem frete de novo na margem da captação.
- Sem saldo: custo indisponível (sem fallback por última venda) — [ADR-0073](ADR-0073-captacao-app-custo-preco-margem-um.md) atualizada.

### CO na venda (refino da ADR-0063)

- **Saída física = galpão ou loja (não-HUB):** CO = **0** na margem (já embutido no PM do estoque da unidade).
- **Saída física = HUB (`is_hub`):** snapshot do CO vigente em R$/kg, **somente em** `resultado_movimentacao`, sem alterar PM do HUB.
- **UN do CO:** `cliente.id_praca` → `pracas.id_unidade_negocio` ([ADR-0058](ADR-0058-cliente-praca-filtrada-por-unidade.md)); usar `HistoricoCOUnNg` vigente dessa UN. O `cliente.id_unidade_negocio` permanece a UN de **faturamento/NF** (ex.: Barbalha), distinta do CO da praça quando aplicável ([ADR-0064](ADR-0064-galpoes-operacionais-venda-tres-eixos.md)).

### PDF “CO mês seguinte”

- Modelo operacional adotado: **CO no PM via transferência/compra** + **CO explícito na margem só na venda HUB**; não implementar apuração “mês seguinte por kg vendido” no MVP do pacote.

## Alternativas consideradas

- **Fallback última venda para custo na captação** — rejeitado; único modelo = PM do estoque.
- **CO na margem de toda venda com saída HUB usando só faturamento** — rejeitado; usar praça do cliente.
- **CO mensal com retificação (PDF)** — rejeitado no MVP; PM + snapshot na venda.

## Consequências

- Atualizar testes ADR-0063 para CO da UN da praça quando aplicável.
- [PLAN-0077](../plans/PLAN-0077-custo-embutido-pm-e-co-venda-hub-praca.md); revisar `ResolverCustoReferenciaCaptacaoService` no PLAN-0073.
