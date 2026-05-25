# ADR-0098: Portão da captação por quantidade; vínculo de rota até vendas finalizadas

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz de captação e pipeline do lote ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md), [ADR-0093](ADR-0093-matriz-abas-quantidade-rotas.md))

## Contexto

A finalização da captação exigia rota em todo pedido com quantidade e congelava o vínculo de rota ao sair de `CAPTACAO_EM_ANDAMENTO`. A operação precisa encerrar a captação quando todas as lojas da aba Quantidade estiverem concluídas, mas continuar roteirizando (abas Rotas e Por rota) ao longo do pipeline até Jefferson finalizar vendas.

## Decisão

- **Finalizar captação (faturamento):** exige apenas que todas as lojas elegíveis da carteira estejam com `captacao_concluida = true` na aba Quantidade (regra [ADR-0087](ADR-0087-captacao-por-loja-conclusao-margem-alvo.md)). **Não** exige rota vinculada.
- **Vínculo de rota** (`id_captacao_rota`, ordem de carregamento, motorista): editável em qualquer status **exceto** `VENDAS_FINALIZADAS` (`permiteEdicaoVinculoRota()`).
- **Finalizar vendas:** valida que todo pedido com quantidade > 0 possui rota; só então o vínculo de rota se considera concluído para o lote.

## Alternativas consideradas

- Manter rota obrigatória na finalização da captação — rejeitada; atrasa Lucas enquanto logística ainda roteiriza.
- Permitir rota após vendas finalizadas — rejeitada; movimentações já efetivadas.

## Consequências

- Romaneio 1 pode ser consolidado com rotas incompletas; atualizar conforme rotas forem preenchidas até finalizar vendas.
- [PLAN-0098](../plans/PLAN-0098-captacao-portao-quantidade-vinculo-rota-pos-vendas.md).
- Atualiza interpretação parcial de [ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md) quanto a bloqueio por rota na captação.
