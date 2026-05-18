# PLAN-0005: ICMS zerado para produtos sem correspondência na tabela legislativa

**ADR:** [ADR-0005](../decisions/ADR-0005-icms-zero-produtos-sem-correspondencia.md)
**Data:** 2026-05-17
**Status:** Concluído

## Objetivo

Preencher com `0` os campos de ICMS dos produtos sem entrada na IN SEFAZ Nº 80/2019 (CE), viabilizando a importação sem erros.

## Pré-requisitos

- Planilha `planilhas/MATERIAIS V2.xlsx` com colunas E–H presentes
- Lista dos produtos sem correspondência na tabela legislativa

## Passos

1. **Identificar produtos sem ICMS** — listar os 25 produtos sem entrada na tabela: banana (todas variações), goiaba, limão, melão, mamão, tomate, abacate
2. **Preencher com zero** — definir `ICMS_EX_COMPRA = 0`, `ICMS_NA_COMPRA = 0` em todos esses registros
3. **Definir UM_ICMS** — preencher `UM_ICMS = KG` como padrão
4. **Sinalizar para revisão fiscal** — comunicar ao setor fiscal a lista de produtos com ICMS zerado para validação futura
5. **Importar** — executar a importação da planilha no sistema

## Critério de conclusão

Todos os 44 registros da planilha possuem valores nas colunas E, F e G (nenhuma célula vazia). Importação concluída sem erros de validação.

## Riscos

- Operações de compra com ICMS incorreto antes da revisão fiscal — não utilizar esses produtos em lançamentos fiscais até que os valores sejam confirmados pelo setor responsável
