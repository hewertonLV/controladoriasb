# ADR-0036: Importação de estoques por UM e preço total

**Data:** 2026-05-21
**Status:** Aceito
**Contexto:** Tela Importar Estoques

## Contexto

A planilha de carga inicial usava quantidade em kg e preço médio por kg. A operação informa posição pela unidade de medição da fruta e pelo valor total da posição.

## Decisão

Layout fixo da planilha (linha 1 = cabeçalho):

| Coluna | Campo |
|--------|--------|
| A | ID CIGAM unidade de negócio |
| B | ID CIGAM fruta |
| C | Quantidade na unidade de medição (UM) |
| D | Preço total (R$) da posição |

O sistema deriva antes de gravar:

- `qtd_fruta_kg` = `qtd_fruta_um` × `kg_por_unidade_medicao` da fruta
- `preco_medio_kg` = `valor_total` ÷ `qtd_fruta_kg` (0 se kg = 0)
- `preco_medio_um` e `valor_total_acumulado` via `EstoqueMovimentacaoService::definirPosicaoAbsoluta`

Preview compara posição existente por UM e valor total acumulado.

Quantidade na UM pode ser **negativa, zero ou positiva** na importação; ver ADR-0045.

## Alternativas consideradas

- Manter kg e R$/kg na planilha — rejeitado; não reflete como a operação lança o estoque inicial.
- Exigir preço médio por UM na planilha — rejeitado; duas colunas (UM + total) bastam e evitam erro de inconsistência entre preços.

## Consequências

- Modelo `planilhas/estoques_importacao.xlsx` e textos da tela atualizados.
- Confirmação continua usando kg e preço médio/kg derivados internamente.
