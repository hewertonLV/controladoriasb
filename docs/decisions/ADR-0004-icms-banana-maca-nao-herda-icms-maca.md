# ADR-0004: "Banana Maçã" não herda o ICMS de "Maçã"

**Data:** 2026-05-17  
**Status:** Aceito  
**Contexto:** Preenchimento de ICMS na planilha `planilhas/MATERIAIS V2.xlsx`

## Contexto

Durante o preenchimento automático de ICMS, o produto `BANANA MAÇA 15KG F` foi inicialmente correspondido ao ICMS de Maçã (0,36/0,18) por conter a palavra "MAÇA" no nome. "Banana Maçã" é uma variedade de banana nomeada pela semelhança de sabor com a maçã, não uma maçã.

## Decisão

Produtos cujo nome começa com "BANANA" não herdam ICMS de "MAÇA/MAÇÃ", mesmo que o nome contenha essa palavra. O ICMS de `BANANA MAÇA` foi definido como `0` (sem correspondência na tabela legislativa), igual às demais variedades de banana.

## Alternativas consideradas

- Aplicar ICMS de Maçã por correspondência de substring: rejeitado — semanticamente incorreto; trata-se de uma banana, não de uma maçã.
- Criar entrada específica "BANANA MAÇA" na tabela ICMS: rejeitado — a Instrução Normativa não prevê essa categoria; aplicar ICMS de banana quando disponível.

## Consequências

- `BANANA MAÇA 15KG F` fica com ICMS `0` até que o valor correto para banana seja identificado na legislação.
- A regra de exclusão (BANANA não herda MAÇA) está codificada no script de preenchimento desta conversa — deve ser replicada se o processo for automatizado.
