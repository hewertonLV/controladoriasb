# PLAN-0156: UI demandas e pendências na conclusão de rota

**ADR:** [ADR-0156](../decisions/ADR-0156-ui-demandas-pendencias-rota.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Tooltip legível para pendências de conclusão na matriz e cards de demandas de transferência/venda na tela inicial dos módulos Movimentação.

## Passos

1. `CaptacaoDemandasRotaExibicaoService` + listagem por módulo (`cardsTransferenciaModulo`, `cardsVendaModulo`).
2. Partial `_demandas-modulo-grid.blade.php` (estilo `captacao-loja-card`) nos index de Transferências e Vendas.
3. Páginas de detalhe com ações (iniciar, CIGAM, NF, efetivar venda).
4. Matriz: pendências em toast; **sem** cards de demanda na aba Por rota.
5. Testes de payload JSON e index dos módulos.

## Critério de conclusão

- Clique em Concluir com pendências mostra tooltip com lista legível.
- Demandas pendentes aparecem como cards nos módulos Transferências e Vendas; matriz não lista demandas.

## Riscos

- Tooltip HTML requer `data-bs-html="true"` — sanitizar conteúdo (apenas textos do servidor).
