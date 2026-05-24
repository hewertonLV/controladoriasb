# PLAN-0074: Romaneio manual de abastecimento sem captação

**ADR:** [ADR-0074](../decisions/ADR-0074-romaneio-manual-abastecimento-sem-captacao.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Permitir criar lote tipo romaneio manual (fruta, qtd, origem), gerar Romaneio 2 e liberar Lucas sem pedidos nem Atanásio.

## Pré-requisitos

- Modelo de lote operacional (PLAN-0066).
- Pipeline Lucas (PLAN-0067 + 0072).

## Passos

1. Migration — `tipo_lote` em lote; tabela `romaneio_manual_linhas` (fruta, qtd, origem, lote_id).
2. Tela admin — criar/editar linhas; validar origem/destino.
3. `ConfirmarRomaneioManualService` — Romaneio 2 estoque vs a receber; status `AGUARDANDO_TRANSFERENCIA_CIGAN`.
4. Integrar listagem Lucas — badge “Manual” vs “Captação”.
5. Bloquear etapas Jefferson para `tipo_lote = ROMANEIO_MANUAL`.
6. Testes — fluxo manual até `TRANSFERENCIA_FINALIZADA` sem pedidos.

## Critério de conclusão

- Operador monta quantidades manuais → confirma → Lucas vê e transfere.
- Lote de captação de pedidos no mesmo galpão/dia não é misturado.

## Ordem

Após PLAN-0066 passo 1; antes ou junto PLAN-0067.
