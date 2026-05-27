# PLAN-0128: Remover loja da matriz de captação

**ADR:** [ADR-0128](../decisions/ADR-0128-matriz-remover-loja-captacao.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Permitir remover loja incluída por engano na matriz, via select, sem recarregar a página.

## Passos

1. `PedidoService::removerLojaDaMatriz` + rota/controller/request.
2. Select e JS na matriz (`removerLojaNaMatriz` + `reconstruirMatrizCaptacao`).
3. Testes feature.

## Critério de conclusão

- Remover loja em captação atualiza matriz via AJAX; fora de captação retorna erro.
