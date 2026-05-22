# ADR-0044: Unidade de medição BDJ (bandeja)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cadastro e importação de frutas

## Contexto

Operação passou a usar **bandeja** como unidade de medição distinta de caixa, pacote, PCT, saco e unidade.

## Decisão

- Incluir `BDJ` em `FrutaUnidadeMedicao`, rótulo **BDJ — Bandeja**.
- Alias de planilha: `BDJ` e `BANDEJA` → `BDJ`.
- Peso por unidade em **kg** com **2 casas decimais** (mesmo padrão de caixa/saco; sem regra especial como PCT).

## Alternativas consideradas

- Mapear BDJ para CAIXA — rejeitado: conceitos operacionais diferentes.
- Três casas decimais como PCT — rejeitado: não foi pedido precisão em gramas para bandeja.

## Consequências

- Importação de materiais com coluna C = `BDJ` ou `BANDEJA` cadastra fruta com UM `BDJ`.
- Movimentações e estoque exibem `BDJ` conforme cadastro da fruta.
