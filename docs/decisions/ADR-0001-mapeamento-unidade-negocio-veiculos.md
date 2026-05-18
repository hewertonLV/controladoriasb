# ADR-0001: Mapeamento de código legado "8" para id_cigam "000400" na importação de veículos

**Data:** 2026-05-17  
**Status:** Aceito  
**Contexto:** Planilha `planilhas/veiculos.xlsx` — importação de veículos

## Contexto

A planilha de veículos possuía o valor `8` na coluna de unidade de negócio, proveniente de um sistema legado (id interno). O importador (`VeiculoPlanilhaNormalizer`) exige o campo `id_cigam` da unidade de negócio com até 6 dígitos numéricos e busca o registro correspondente em `unidades_negocio`.

## Decisão

O valor `8` foi mapeado para o `id_cigam` `000400`, que corresponde à unidade de negócio correta no cadastro do sistema.

## Alternativas consideradas

- Manter `8` como `id_cigam`: rejeitado — nenhuma unidade cadastrada com esse código.
- Solicitar nova exportação do ERP com id_cigam correto: rejeitado — processo mais lento; o mapeamento direto é suficiente para a carga inicial.

## Consequências

- Todos os veículos da planilha serão associados à unidade `000400`.
- Se no futuro houver veículos de outras unidades, a planilha precisará de uma coluna `id_cigam` com valores distintos por linha.
- O código `8` (legado) não está documentado no sistema — manter referência cruzada fora do ERP.
