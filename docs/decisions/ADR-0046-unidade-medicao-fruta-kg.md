# ADR-0046: Unidade de medição KG (quilograma)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cadastro e importação de frutas

## Contexto

Algumas frutas são controladas diretamente em **quilograma** na operação, sem caixa, pacote ou bandeja.

## Decisão

- Incluir `KG` em `FrutaUnidadeMedicao`, rótulo **KG — Quilograma**.
- Aliases de planilha: `KG` e `QUILOGRAMA` → `KG`.
- `kg_por_unidade_medicao` em geral é **1,00** (1 kg por unidade KG); o valor continua editável na planilha e no formulário.
- Três casas decimais em `kg_por_unidade_medicao` quando a UM é **KG** (mesma precisão de grama que PCT).

## Alternativas consideradas

- Inferir UM KG quando coluna D = 1 — rejeitado: explícito na coluna C evita ambiguidade.
- Não usar casas extras para KG — rejeitado: operação pode lançar frações de kg com precisão de grama.

## Consequências

- Movimentações: `qtd_fruta_um` coincide com kg quando `kg_por_unidade_medicao = 1`.
- Formulário orienta o uso de 1,00 kg por unidade.
