# ADR-0165: Matriz sem timeline e fechamento do lote após rotas

**Data:** 2026-05-28
**Status:** Aceito (revisado)
**Contexto:** Novo fluxo por rota na matriz; regras loja↔rota em [ADR-0152](ADR-0152-rota-concluida-matriz-por-rota.md).

## Contexto

A linha do tempo do pipeline legado na matriz não reflete o fluxo por rota. Houve tentativa de inverter a ordem loja/rota em versão anterior desta ADR — **revogada**; a regra canônica permanece na ADR-0152.

## Decisão

- Remover a linha do tempo do lote da tela **matriz**; manter em detalhe do lote/romaneio quando útil.
- **Concluir captação do lote** (`CAPTACAO_CONCLUIDA`, [ADR-0166](ADR-0166-captacao-lote-status-concluida.md)) exige todas as rotas com pedido concluídas e todas as lojas com quantidade com `captacao_concluida`.
- **Interação loja ↔ rota** (concluir/reabrir loja, concluir rota): ver [ADR-0152](ADR-0152-rota-concluida-matriz-por-rota.md) — sem duplicar aqui.

## Alternativas consideradas

- Documentar loja↔rota nesta ADR — rejeitado: duplicaria ADR-0152.

## Consequências

- Uma única fonte para regras rota/loja: ADR-0152.
- Poll `matriz.estado` expõe `rotas_pendentes_conclusao_captacao` apenas para o botão de fechar o **lote**.
