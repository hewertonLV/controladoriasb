# PLAN-0153: Romaneio de carregamento por rota

**ADR:** [ADR-0153](../decisions/ADR-0153-romaneio-carregamento-por-rota.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Exibir romaneio de carregamento por rota com ordem de carregamento e remover aba Romaneio 2 da UI.

## Pré-requisitos

- Rotas e ordem de carregamento na matriz ([ADR-0152](ADR-0152-rota-concluida-matriz-por-rota.md)).

## Passos

1. **Domínio** — `RomaneioCarregamentoService::previewPorRotas()` com ordem e metadados da rota.
2. **Views** — partial de abas por rota; coluna Ordem nas tabelas; remover Romaneio 2 de `lotes/show` e romaneio manual.
3. **Controllers** — passar `romaneiosCarregamentoPorRota` na matriz (saída físico) e no show do lote.
4. **Testes** — ordenação, título da aba, ausência de Romaneio 2 no show.

## Critério de conclusão

- Lote show e matriz saída físico com abas `{carteira} — {rota}`; ordem visível; sem aba Romaneio 2; testes verdes.

## Riscos

- Lojas sem rota fora do romaneio — mitigação: vincular na aba Rotas antes do carregamento.
