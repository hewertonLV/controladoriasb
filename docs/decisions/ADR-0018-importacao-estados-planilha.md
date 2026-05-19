# ADR-0018: Importação de estados por planilha Excel

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** CRUD de estados sem fluxo de importação em massa.

## Decisão

Planilha com colunas fixas: **A** nome, **B** sigla (2 letras), **C** descrição (opcional). Chave de correspondência: **abreviacao**. Preview assíncrono (job + confirmação) igual aos demais cadastros. Permissões `estados.importar` e `estados.importar-confirmar`.

> **Atualização:** layout e chave revisados em [ADR-0019](ADR-0019-importacao-estados-colunas-id-cigam.md) (A=id_cigam, B=nome, C=abreviação, D=descrição; chave `id_cigam`).

## Alternativas consideradas

- Importação síncrona na requisição — rejeitado (padrão do projeto com fila).
- Chave por nome — rejeitado; sigla é o identificador operacional (ICMS, planilhas legadas).

## Consequências

- Estados inativos (soft delete) podem ser reativados ao confirmar atualização da planilha.
