# PLAN-0091: Rotas de captação vinculadas à carteira

**ADR:** [ADR-0091](../decisions/ADR-0091-rotas-captacao-vinculo-carteira.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Migrar rotas de galpão para carteira e ajustar cadastro, listagem e validação de pedido.

## Passos

1. **Migration** — `id_captacao_carteira`, backfill, remover `id_unidade_negocio_galpao`.
2. **Model + CRUD** — formulário e filtros por carteira.
3. **PedidoService** — validar rota pertence à carteira do lote ao gravar `id_captacao_rota`.
4. **Testes** — `CaptacaoRotaTest` e cenário básico.

## Critério de conclusão

- Rotas cadastradas com carteira; testes verdes.

## Riscos

- Backfill com várias carteiras no mesmo galpão — mitigação: menor `id`; revisar manualmente se necessário.
