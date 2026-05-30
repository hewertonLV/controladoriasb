# PLAN-0152: Concluir rota na aba Por rota

**ADR:** [ADR-0152](../decisions/ADR-0152-rota-concluida-matriz-por-rota.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Permitir concluir e reabrir rotas na aba Por rota, bloqueando vínculos e edições na rota fechada.

## Pré-requisitos

- Tabela `captacao_lote_rotas` existente (ADR-0134).

## Passos

1. **Migration** — adicionar `concluida` boolean default false em `captacao_lote_rotas`.
2. **Domínio** — métodos concluir/reabrir e validação em `CaptacaoMatrizRotasService` e `PedidoService`.
3. **HTTP** — rotas POST concluir/reabrir no `CaptacaoMatrizController`.
4. **UI** — botões na aba Por rota; desabilitar selects na aba Rotas para rotas concluídas.
5. **Testes** — feature tests de bloqueio e reabertura.

## Critério de conclusão

- Concluir rota impede PATCH de rota/ordem/motorista/veículo; reabrir restaura; testes verdes.

## Riscos

- Rota concluída sem registro em `captacao_lote_rotas` — mitigação: `updateOrCreate` ao concluir.
