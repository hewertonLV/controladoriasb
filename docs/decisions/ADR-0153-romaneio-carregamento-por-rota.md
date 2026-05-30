# ADR-0153: Romaneio de carregamento por rota

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Romaneios de captação — carregamento e abastecimento

## Contexto

O Romaneio 2 (abastecimento) como aba dedicada deixará de ser usado na UI; um novo modelo será desenhado depois. O Romaneio 1 (carregamento) precisa refletir a operação real: **uma carga por rota**, com lojas na **ordem de carregamento** definida na matriz.

## Decisão

- Remover a aba **Romaneio 2 — Abastecimento** das telas de lote e romaneio manual (o serviço `RomaneioAbastecimentoService` permanece para Cigan/transferência).
- Romaneio de carregamento: **uma aba por rota** do lote, com rótulo `{carteira} — {rota}`.
- Lojas ordenadas por `ordem_carregamento` (depois nome); coluna **Ordem** visível na tabela.
- Cabeçalho de cada aba exibe motorista e veículo de `captacao_lote_rotas`, quando informados.
- Lojas sem rota vinculada não entram no romaneio por rota.

## Alternativas consideradas

- **Manter Romaneio 2 na UI** — rejeitado; operação vai migrar para novo modelo.
- **Romaneio único com coluna rota** — rejeitado; operador precisa de documento separado por veículo/rota.

## Consequências

- [PLAN-0153](../plans/PLAN-0153-romaneio-carregamento-por-rota.md).
- Prévia de abastecimento na aba Arquivo Cigan (transferência) permanece; texto deixa de citar «Romaneio 2».
