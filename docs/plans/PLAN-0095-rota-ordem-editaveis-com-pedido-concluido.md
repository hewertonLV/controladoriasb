# PLAN-0095: Rota e ordem editáveis com pedido concluído

**ADR:** [ADR-0095](../decisions/ADR-0095-rota-ordem-editaveis-com-pedido-concluido.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Permitir vincular/alterar rota e ordem de carregamento na matriz mesmo com a captação da loja concluída.

## Pré-requisitos

- Endpoints PATCH de rota e ordem já existentes (ADR-0093/0094).

## Passos

1. **PedidoService** — remover validação de `captacao_concluida` em `atualizarRotaPedido` e `atualizarOrdemCarregamento`.
2. **Views/JS** — habilitar selects nas abas Rotas e Por rota; não desabilitar no sync de conclusão.
3. **Testes** — cobrir rota e ordem com loja concluída.

## Critério de conclusão

- PATCH rota/ordem retorna 200 com loja concluída; quantidade continua bloqueada; testes verdes.

## Riscos

- Alteração de rota após conclusão limpa ordem (regra ADR-0094) — comportamento mantido.
