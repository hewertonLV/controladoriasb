# PLAN-0139: Replay de vendas com saída física HUB e CO embutido

**ADR:** [ADR-0139](../decisions/ADR-0139-replay-venda-hub-saida-fisica-co-embutido.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Garantir replay e UI corretos para vendas com saída HUB e CO embutido; corrigir NF legadas.

## Passos

1. Ajustar `ReplayLinhaTempoEstoqueService` (eventos + aplicarSaida).
2. Criar `CorrigirCustosVendaSaidaHubService` + comando artisan.
3. Melhorar `show.blade.php` (saída física + CO total).
4. Testes + corrigir NF CAP-20260527-14-116.
5. Teste de cancelamento hub com reversão de realocação — ver [PLAN-0140](PLAN-0140-cancelamento-venda-hub-reverte-realocacao.md).

## Critério de conclusão

- Testes verdes.
- NF 116 com `valor_custo_saida` = PM×kg + CO faturamento×kg e observação HUB preenchida.

## Riscos

- Replay altera saldo HUB — mitigação: correção só em vendas com divergência detectável.
