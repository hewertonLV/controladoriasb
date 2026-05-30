# PLAN-0164: Demanda de venda agregada por rota (romaneio)

**ADR:** [ADR-0164](../decisions/ADR-0164-demanda-venda-rota-agregada.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Uma demanda de venda por rota com linhas loja×fruta, romaneio na UI, CIGAM por rota e efetivação manual única.

## Pré-requisitos

- ADR-0162 (linhas de demanda) e soft delete em demandas ([ADR-0163](ADR-0163-soft-delete-demandas-captacao.md)).

## Passos

1. **Migration** — `id_pedido`, `preco_venda` e unique `(demanda, fruta, pedido)` em `captacao_lote_movimentacao_linhas`; consolidar vendas legadas por rota.
2. **Registro na conclusão** — `registrarDemandaVendaRota` substitui loop por pedido; sincronizar linhas do romaneio.
3. **Efetivação** — `CaptacaoDemandaVendaRotaService` processa todas as lojas da demanda; cria/conclui `VendaNota` por loja.
4. **CIGAM** — `gerarPorDemanda` + rota HTTP de download na demanda.
5. **UI** — card/título da rota, tabela romaneio, botão CIGAM.
6. **Testes** — ajustar `CaptacaoMatrizTest` e adicionar cenário multi-loja.

## Critério de conclusão

Concluir rota com N lojas gera 1 demanda `VENDA_NOTA` com N×frutas linhas; CIGAM baixa blocos por loja; efetivar gera movimentações por loja; testes verdes.

## Riscos

- Dados legados por loja — mitigado pela migration de consolidação.
- Unique com `id_pedido` nulo — mitigado por coluna gerada `id_pedido_key`.
