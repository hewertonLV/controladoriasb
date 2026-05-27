# PLAN-0031: Relatório de rentabilidade por loja

**ADR:** [ADR-0031](../decisions/ADR-0031-relatorio-rentabilidade-loja.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Entregar tela de relatório com vendas, devoluções, custo de origem e resultado líquido por cliente.

## Pré-requisitos

- Movimentações de venda e devolução com snapshots de custo/resultado

## Passos

1. **Permissão** — `relatorios.rentabilidade-loja.visualizar` em `Permissions` + seeder
2. **Serviço** — `RentabilidadeLojaService` agrega vendas e devoluções
3. **HTTP** — controller, request, rota, sidebar
4. **View** — filtros + tabela + totais
5. **Teste** — cenário venda + devolução parcial

## Critério de conclusão

- Relatório acessível no menu; filtros funcionam; totais batem com movimentações de teste

## Riscos

- Volume grande sem paginação — mitigar com filtro de período obrigatório (máx. 366 dias)
