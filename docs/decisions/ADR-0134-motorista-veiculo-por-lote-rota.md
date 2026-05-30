# ADR-0134: Motorista e veículo por lote e rota (não na carteira)

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Matriz aba Por rota ([ADR-0100](ADR-0100-matriz-veiculo-exclusivo-por-rota-carteira.md), [ADR-0133](ADR-0133-status-vincular-rotas-pos-nf-venda.md))

## Contexto

`nome_motorista` e `id_veiculo` ficavam em `captacao_rotas` (nível carteira). Ao alterar veículo ou motorista em um lote, outro lote da mesma carteira via o mesmo cadastro de rota e herdava o valor.

## Decisão

- Criar `captacao_lote_rotas` (`id_captacao_lote`, `id_captacao_rota`, `nome_motorista`, `id_veiculo`) com unique por par lote+rota.
- A matriz (aba Por rota) grava e lê **somente** dessa tabela.
- Exclusividade de veículo: no **mesmo lote**, um veículo ativo em no máximo uma rota (restringe ADR-0100 ao lote, não à carteira).
- `captacao_rotas` deixa de ser atualizada pela matriz; cadastro de rotas na carteira mantém só nome/ativo (veículo legado na tabela ignorado na matriz).

## Alternativas consideradas

- Manter em `captacao_rotas` e copiar ao criar lote — rejeitado; edição posterior ainda vazaria entre lotes.
- Exclusividade global entre lotes — rejeitado; captações do mesmo dia podem reutilizar frota em rotas diferentes.

## Consequências

- [PLAN-0134](../plans/PLAN-0134-motorista-veiculo-por-lote-rota.md).
- Migration com backfill a partir de `captacao_rotas` + pedidos existentes.
