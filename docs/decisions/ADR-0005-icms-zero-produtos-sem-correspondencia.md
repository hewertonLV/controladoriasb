# ADR-0005: ICMS zerado para produtos sem correspondência na tabela legislativa

**Data:** 2026-05-17  
**Status:** Aceito  
**Contexto:** Preenchimento de ICMS na planilha `planilhas/MATERIAIS V2.xlsx`

## Contexto

25 dos 44 materiais (banana em todas as variações, goiaba, limão, melão, mamão, tomate, abacate) não possuem entrada na Instrução Normativa SEFAZ Nº 80/2019 (CE) utilizada como referência. O importador assume `0` quando os campos de ICMS estão em branco.

## Decisão

Os campos `ICMS_EX_COMPRA`, `ICMS_NA_COMPRA` foram preenchidos com `0` e `UM_ICMS` com `KG` para esses produtos, permitindo a importação sem erros.

## Alternativas consideradas

- Deixar em branco: aceito pelo importador (assume 0), mas explícito é melhor para auditoria.
- Bloquear importação até obter os valores corretos: rejeitado — impede o cadastro inicial; valores podem ser ajustados por produto após a importação.
- Buscar outra tabela legislativa: pendente — a IN 80/2019 (CE) não cobre todos os produtos do portfólio.

## Consequências

- Produtos com ICMS `0` podem gerar inconsistências fiscais se utilizados em operações de compra antes de os valores serem corrigidos.
- Recomenda-se revisão dos valores de ICMS para banana (principal volume) com o setor fiscal antes de uso em produção.
