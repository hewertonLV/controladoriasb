# PLAN-0083: Romaneio manual estilo matriz e pipeline sequencial

**ADR:** [ADR-0083](../decisions/ADR-0083-romaneio-manual-matriz-e-pipeline-sequencial.md)
**Data:** 2026-05-23
**Status:** Concluído

## Objetivo

Romaneio manual com autosave por fruta/caixa e botões de pipeline sequenciais nas telas de captação e romaneio.

## Pré-requisitos

- ADR-0074 e pipeline Lucas (ADR-0067) implementados.

## Passos

1. **Serviço e API** — `RomaneioManualService`, rotas JSON e `ConcluirTransferenciaRomaneioManualAction`.
2. **UI romaneio** — `create` mínimo; `edit` com matriz de linhas e badge de sync.
3. **Pipeline UI** — `CaptacaoLotePipelineUi`, partial `_lote-pipeline-acoes`, uso em `lotes/show` e romaneio.
4. **Testes** — autosave, pipeline manual, botão único no lote.

## Critério de conclusão

- Romaneio manual editável com autosave; três etapas sequenciais na tela; lote de captação mostra só a próxima ação; testes passando.

## Riscos

- Concluir transferência sem estoque na origem — mitigado por validação existente de transferência; testes com hub + estoque.
