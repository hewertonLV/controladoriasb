# PLAN-0042: Unidade de medição PCT (pacote) com peso em kg

**ADR:** [ADR-0042](../decisions/ADR-0042-unidade-medicao-fruta-pct-pacote-kg.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Cadastrar e importar frutas com UM **PCT**, representando peso de pacote em kg com precisão de grama (3 decimais).

## Pré-requisitos

- ADR-0042 aceita
- Tabela `frutas` existente

## Passos

1. **Enum** — adicionar `PCT` em `FrutaUnidadeMedicao` com `rotulo()` e `casasDecimaisKg()`.
2. **Migration** — `kg_por_unidade_medicao` para `decimal(15,3)`.
3. **Normalizer/importação** — `PCT` → `PCT`; kg com 3 casas quando UM é PCT.
4. **Model** — mutator e cast alinhados à precisão por UM.
5. **UI** — select com rótulos; ajuda e `step` dinâmico no formulário; listagem/PDF com casas corretas.
6. **Testes** — cadastro PCT, normalizer e importação.

## Critério de conclusão

- Fruta salva com `unidade_medicao = PCT` e `kg_por_unidade_medicao = 0.500` para entrada 0,5 kg.
- Planilha com `PCT` não vira `PACOTE`.
- Testes de frutas/importação passam.

## Riscos

- Registros legados importados como PACOTE via alias PCT — revisar cadastro manual se necessário.
