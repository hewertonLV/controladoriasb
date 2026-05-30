# PLAN-0161: Vincular frete na demanda manual de transferência

**ADR:** [ADR-0161](../decisions/ADR-0161-vincular-frete-demanda-manual.md)
**Data:** 2026-05-28
**Status:** Pendente

## Objetivo

Inserir etapa `VINCULAR_FRETE` após NF na demanda manual; movimentação SB só após frete vinculado ou «Sem frete».

## Pré-requisitos

- [PLAN-0160](PLAN-0160-demanda-transferencia-manual-multi-fruta.md) com upload NF levando a `VINCULAR_FRETE` (não `CONCLUIDO`).

## Passos

1. **Enum/status** — adicionar `VINCULAR_FRETE` ao ciclo da demanda manual.
2. **Upload NF** — ao validar NF, transição `INICIADO` → `VINCULAR_FRETE` (sem movimentação).
3. **UI** — tela/section: select fretes ABERTOS (`VincularFreteTransferenciaRequest` / listagem existente); botão «Sem frete»; botão «Concluir» desabilitado até uma escolha.
4. **Concluir** — endpoint persiste `id_frete` ou null explícito; status → `CONCLUIDO`; chamar geração de movimentação(ões) + rateio frete se houver ([ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md)).
5. **Testes** — NF → VINCULAR_FRETE; sem escolha não conclui; com frete e sem frete → movimentação criada.

## Critério de conclusão

- Fluxo manual completo: NF anexada → vincular frete ou sem frete → estoque movimentado.

## Riscos

- Multi-fruta numa demanda: frete único por demanda ou por par de movimentação — definir no backend (padrão: um frete por demanda aplicado às pernas geradas).
