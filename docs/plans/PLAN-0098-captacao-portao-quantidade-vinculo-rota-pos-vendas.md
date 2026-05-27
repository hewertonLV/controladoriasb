# PLAN-0098: Portão da captação por quantidade; vínculo de rota até vendas finalizadas

**ADR:** [ADR-0098](../decisions/ADR-0098-captacao-portao-quantidade-vinculo-rota-pos-vendas.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Desacoplar finalização da captação do vínculo de rota e exigir rotas completas apenas ao finalizar vendas.

## Pré-requisitos

- Abas Quantidade/Rotas/Por rota na matriz.
- Pipeline Lucas/Jefferson operacional.

## Passos

1. **`CaptacaoLoteStatus::permiteEdicaoVinculoRota()`** — true até antes de `VENDAS_FINALIZADAS`.
2. **`FinalizarCaptacaoFaturamentoAction`** — remover validação de rota; manter lojas concluídas.
3. **`PedidoService`** — rota/ordem usam `assertLotePermiteVinculoRota`; `assertPedidosComQuantidadeTemRota` público.
4. **`FinalizarVendasLoteAction`** — chamar validação de rota antes de gerar vendas.
5. **Views matriz** — selects de rota/ordem/motorista enquanto `permiteEdicaoVinculoRota`.
6. **Testes** — finalizar captação sem rota OK; finalizar vendas sem rota bloqueado; rota após captação OK.

## Critério de conclusão

Regras aplicadas em actions, service, UI e testes verdes.

## Riscos

- Romaneio 1 parcialmente sem rota — mitigação: operação preenche antes de Jefferson; bloqueio explícito em finalizar vendas.
