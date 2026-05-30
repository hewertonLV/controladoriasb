# ADR-0161: Vincular frete na demanda manual de transferência

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Demanda manual de transferência ([ADR-0160](ADR-0160-demanda-transferencia-manual-multi-fruta.md)); padrão de frete em transferências ([ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md), [ADR-0072](ADR-0072-vinculo-frete-pos-transferencia-lote.md))

## Contexto

Após o operador anexar a NF de transferência na demanda manual, a operação precisa vincular (ou recusar explicitamente) um frete **antes** de efetivar a movimentação no SB — mesmo padrão do pipeline de transferência por lote, porém no ciclo da demanda individual.

## Decisão

- **Gatilho:** upload válido da NF na demanda manual em status `INICIADO` → transição para **`VINCULAR_FRETE`** (não vai direto para `CONCLUIDO`).
- **Tela/ação:** listar fretes **ABERTOS** elegíveis (mesmo critério de [ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md)); operador seleciona um frete **ou** aciona explicitamente **Sem frete** — ambos são obrigatórios como escolha consciente (não avança sem uma das opções).
- **Com frete:** persistir `id_frete` no(s) par(es) de movimentação gerado(s) ao concluir; rateio via `FreteRateioMovimentacaoService` após movimentação conforme ADR-0003.
- **Sem frete:** registrar decisão explícita (`id_frete = null`); transição permitida sem bloqueio.
- **Conclusão:** após vínculo ou «Sem frete», status → **`CONCLUIDO`** e **somente então** executar movimentação(ões) de transferência no SB.
- **Escopo:** aplica-se **apenas** a demandas manuais ([ADR-0160](ADR-0160-demanda-transferencia-manual-multi-fruta.md)); demandas automáticas da captação ([ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md)) permanecem sem esta etapa até decisão futura.

## Alternativas consideradas

- **Vincular frete antes da NF** — rejeitado; operação pediu NF primeiro, frete depois.
- **Movimentar na NF e frete opcional depois** — rejeitado; movimentação só após frete/sem frete.
- **Reutilizar status de lote `AGUARDANDO_VINCULO_FRETE`** — rejeitado; ciclo é por demanda, não por lote.

## Consequências

- [PLAN-0161](../plans/PLAN-0161-vincular-frete-demanda-manual.md).
- Enum de status da demanda manual inclui `VINCULAR_FRETE` entre `INICIADO` e `CONCLUIDO`.
- UI: após NF, exibir select de frete + botão «Sem frete» + «Concluir transferência».
