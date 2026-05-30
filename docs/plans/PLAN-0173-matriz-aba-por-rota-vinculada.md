# PLAN-0173: Aba dinâmica por rota vinculada na matriz

**ADR:** [ADR-0173](../decisions/ADR-0173-matriz-aba-por-rota-vinculada.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Substituir a aba Por rota por abas individuais por rota vinculada, com layout e funcionalidade equivalentes, atualizadas em tempo real.

## Pré-requisitos

- `grupos_ordem_carregamento` no snapshot da matriz.
- Bootstrap tabs e poll existentes na matriz.

## Passos

1. **Controller** — aceitar `aba=rota-{id}`; mapear `por-rota` para primeira rota.
2. **Views** — partials por rota (cabeçalho + tabela); loop nas abas de navegação e panes.
3. **JS** — `renderOrdemCarregamento` reconstrói abas/panes; delegação de eventos; sync de URL.
4. **Testes** — atualizar `CaptacaoMatrizTest` para abas dinâmicas.

## Critério de conclusão

- Rotas vinculadas geram abas nomeadas pela rota; cada aba mostra só suas lojas.
- Poll adiciona/remove abas sem refresh; URL `aba=rota-{id}` funciona.
- Concluir/reabrir rota, motorista, veículo e ordem continuam operando.

## Riscos

- Muitas rotas na nav — mitigação: `flex-wrap` já existente na nav da matriz.
