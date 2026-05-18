# ADR-0003: Uso da aba "BASE" como fonte canônica da planilha de materiais

**Data:** 2026-05-17  
**Status:** Aceito  
**Contexto:** Planilha `planilhas/MATERIAIS V2.xlsx` — importação de frutas/materiais

## Contexto

A planilha continha três abas: `T.O` (tipos de operação fiscal), `MATERIAIS` (dados com coluna de unidade de medida zerada — valor `0`) e `BASE` (dados com unidade de medida válida — `CX`, `UN`, `PCT`, `SC`).

O importador (`FrutaImportacaoProcessor`) seleciona automaticamente a aba com maior pontuação de compatibilidade de cabeçalhos, mas havia ambiguidade entre `MATERIAIS` e `BASE`.

## Decisão

A aba `BASE` foi definida como a única fonte de dados. As abas `T.O` e `MATERIAIS` foram removidas da planilha. A planilha passou a conter apenas a aba `BASE`.

## Alternativas consideradas

- Manter todas as abas e confiar na seleção automática do importador: rejeitado — risco de o importador escolher `MATERIAIS` (com unidades zeradas), causando falha em todas as linhas.
- Corrigir a coluna de unidade de medida na aba `MATERIAIS`: rejeitado — `BASE` já estava correta e é mais completa.

## Consequências

- A planilha ficou com 44 registros únicos (reduzida de 118 linhas com duplicatas).
- A aba `T.O` (referência fiscal) foi descartada da planilha de importação — deve ser preservada em documento separado se necessária para outros fins.
