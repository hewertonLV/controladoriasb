# PLAN-0093: Abas Quantidade e Rotas na matriz

**ADR:** [ADR-0093](../decisions/ADR-0093-matriz-abas-quantidade-rotas.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Abas Quantidade e Rotas na matriz com vínculo de rota por loja.

## Passos

1. **Serviço** — `CaptacaoMatrizRotasService` (grupos por loja + rotas da carteira).
2. **HTTP** — `PATCH pedidos/{cliente}/rota` + snapshot `linhas_rotas`.
3. **UI** — tabs, tabela Rotas, JS sync/poll.
4. **Testes** — `CaptacaoMatrizTest`.

## Critério de conclusão

- Abas visíveis; rota gravada por loja; testes verdes.

## Riscos

- Tabela Rotas desatualizada entre polls — mitigação: `linhas_rotas` no estado JSON.
