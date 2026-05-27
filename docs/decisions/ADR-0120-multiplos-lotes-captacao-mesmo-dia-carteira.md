# ADR-0120: Vários lotes de captação no mesmo dia × carteira

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Abertura de captação ([ADR-0084](ADR-0084-captacao-dia-carteira-agenda-cliente.md))

## Contexto

A unicidade `(data_referencia, id_captacao_carteira)` impedia uma segunda captação no mesmo dia após finalizar a primeira. A operação precisa reabrir captação na mesma carteira e data quando o lote anterior já saiu de **Captação em andamento**.

## Decisão

- **Remover** o índice único `(data_referencia, id_captacao_carteira)` em `captacao_lotes`.
- Regra de abertura (`abrirOuRecuperarLote`):
  - Se existir lote da carteira na data com status **`CAPTACAO_EM_ANDAMENTO`** (mesmo `tipo`) → **recuperar** esse lote (não criar outro).
  - Caso contrário → **criar** novo lote em `CAPTACAO_EM_ANDAMENTO`.
- Vários lotes no mesmo dia × carteira são permitidos desde que no máximo **um** esteja em andamento por vez.

## Alternativas consideradas

- Manter unicidade e reabrir lote finalizado — rejeitado; mistura pedidos/romaneios de ciclos distintos.
- Permitir dois em andamento — rejeitado; conflito em matriz, pedidos por loja e finalização do faturamento.

## Consequências

- [PLAN-0120](../plans/PLAN-0120-multiplos-lotes-captacao-mesmo-dia-carteira.md).
- Atualiza [ADR-0084](ADR-0084-captacao-dia-carteira-agenda-cliente.md) quanto à unicidade (regra substituída por esta ADR).
