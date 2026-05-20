# ADR-0019: Layout da importação de estados com ID CIGAM

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Planilha legada de estados usa código CIGAM; ADR-0018 previa apenas nome/sigla/descrição.

## Decisão

Colunas fixas: **A** `id_cigam` (até 6 dígitos), **B** nome, **C** abreviação (UF, 2 letras), **D** descrição (opcional). Chave de correspondência na importação: **`id_cigam`**. Campo `id_cigam` obrigatório e único na tabela `estados`; registros existentes recebem backfill `str_pad(id, 6, '0')`.

## Alternativas consideradas

- Manter chave por abreviação — rejeitado; planilha e integração CIGAM usam código numérico.
- Coluna A como nome — rejeitado; não reflete o layout informado pelo negócio.

## Consequências

- ADR-0018 permanece válida no fluxo assíncrono; layout e chave atualizados nesta ADR.
- CRUD manual e listagem passam a exibir/editar `id_cigam`.
- Conflito de sigla com outro estado (id CIGAM diferente) gera erro na prévia.
