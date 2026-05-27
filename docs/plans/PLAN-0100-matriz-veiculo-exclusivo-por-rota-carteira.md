# PLAN-0100: Veículo exclusivo por rota na carteira (matriz)

**ADR:** [ADR-0100](../decisions/ADR-0100-matriz-veiculo-exclusivo-por-rota-carteira.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Impedir que o mesmo veículo apareça ou seja vinculado a mais de uma rota da carteira na aba Por rota da matriz.

## Pré-requisitos

- ADR-0099 (select de veículo na matriz) implementado.

## Passos

1. **Service** — `idsVeiculosOcupadosNaCarteira()` e validação em `atualizarVeiculoRota()`.
2. **Blade/JS** — filtrar opções do select por rota com base em `rotas[].id_veiculo`.
3. **Testes** — feature test com duas rotas e veículo duplicado rejeitado.

## Critério de conclusão

- Veículo vinculado à rota A não aparece no select da rota B.
- PATCH tentando reutilizar veículo retorna 422.
- Testes de matriz passam.

## Riscos

- Rotas de carteiras diferentes compartilhando veículo — mitigado: filtro só na mesma carteira.
