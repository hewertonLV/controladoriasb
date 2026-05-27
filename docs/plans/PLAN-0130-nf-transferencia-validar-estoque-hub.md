# PLAN-0130: Validar estoque HUB antes do upload da NF de transferência

**ADR:** [ADR-0130](../decisions/ADR-0130-nf-transferencia-validar-estoque-hub.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Impedir upload da NF de transferência Cigan quando o HUB selecionado não tiver estoque SB suficiente para as frutas a receber do lote.

## Pré-requisitos

- ADR-0129 (fluxo saída estoque físico após NF).
- Romaneio de abastecimento e `id_unidade_negocio_hub_origem` no lote.

## Passos

1. **Serviço** — `ValidarEstoqueHubNfTransferenciaCiganService` usando `RomaneioAbastecimentoService` + estoque na unidade HUB.
2. **Action + Request** — chamar validação antes de armazenar NF.
3. **Testes** — bloqueio com estoque insuficiente; sucesso com estoque ok.
4. **Status** — marcar plano como Concluído.

## Critério de conclusão

Upload retorna erro de validação amigável quando falta estoque no HUB; passa quando há saldo para todas as frutas com `a_receber` > 0.

## Riscos

- Arredondamento kg/UM — mitigação: mesmas casas decimais do romaneio e comparação em kg (e UM se `a_receber_um` > 0).
