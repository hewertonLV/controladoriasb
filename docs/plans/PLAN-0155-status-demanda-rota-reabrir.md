# PLAN-0155: Status de demanda e reabertura de rota

**ADR:** [ADR-0155](../decisions/ADR-0155-status-demanda-rota-reabrir.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Status Aberto/Iniciado/Concluído nas demandas de transferência e venda por rota, com remoção automática ao reabrir rota (somente se Aberto).

## Passos

1. Migration `status_demanda` em `captacao_lote_movimentacoes`.
2. `CaptacaoDemandaRotaService` — transições, validação reabrir, remoção.
3. `cancelarTransferenciaPendenteRecebimento` no serviço de transferência.
4. Integrar em concluir/reabrir rota, confirmar recebimento e gerar venda.
5. Testes de reabertura permitida/bloqueada e reconclusão.

## Critério de conclusão

- Demandas nascem Abertas; evoluem conforme ADR-0155.
- Reabrir rota com demanda Iniciada/Concluída retorna 422.
- Reabrir com tudo Aberto remove demandas; reconclusão recria com valores atuais.

## Riscos

- Rota com venda já concluída (saída galpão) não pode reabrir — comportamento esperado.
