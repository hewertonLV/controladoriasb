# ADR-0042: Unidade de medição PCT (pacote) com peso em kg

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cadastro e importação de frutas — unidade de medição operacional

## Contexto

Planilhas e operação usam **PCT** para pacotes com peso definido em gramas no dia a dia, mas o sistema persiste peso por unidade em **quilogramas**. Hoje `PCT` na importação de frutas era alias de `PACOTE`, misturando dois conceitos.

## Decisão

- Incluir `PCT` em `FrutaUnidadeMedicao`, distinto de `PACOTE`.
- Manter `PACOTE` / `PC` mapeando para `PACOTE`; `PCT` mapeia apenas para `PCT`.
- Coluna `kg_por_unidade_medicao` passa a `decimal(15,3)`; frutas com UM **PCT** gravam até **3 casas decimais** (ex.: 500 g → `0.500` kg); demais UMs continuam com **2 casas** na persistência.
- Formulário admin orienta conversão g → kg e ajusta `step` do campo quando a UM é PCT.

## Alternativas consideradas

- Manter `PCT` como alias de `PACOTE` — rejeitado: não distingue pacote operacional (PCT) de outro cadastro PACOTE.
- Campo separado em gramas — rejeitado: duplicaria conversão em movimentações que já usam kg por UM.
- Três casas decimais para todas as frutas — rejeitado: desnecessário para caixa/saco com pesos maiores; só PCT exige precisão de grama.

## Consequências

- Importação de materiais com coluna C = `PCT` cria fruta com UM `PCT`, não `PACOTE`.
- ICMS de venda continua usando `FrutaUmIcms::PCT` (percentual), conceito independente da UM de estoque.
- Telas que formatam kg devem usar casas decimais da UM (2 ou 3).
