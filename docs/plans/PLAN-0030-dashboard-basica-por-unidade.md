# PLAN-0030: Dashboard básica por unidade (mesmo da ADR)

**ADR:** [ADR-0030](../decisions/ADR-0030-dashboard-basica-por-unidade.md)
**Data:** 2026-05-20
**Status:** Concluído

## Objetivo

Exibir painel inicial com totais de cadastros e movimentações filtrados pelas unidades do usuário logado.

## Pré-requisitos

- `UnidadeNegocioAccessService` e vínculo `unidade_negocio_user` ativos.

## Passos

1. **Service** — `DashboardStatsService` agrega contagens e listas.
2. **Controller** — `DashboardController@index` substitui closure da rota.
3. **View** — cards, tabela por unidade, tipos e últimas movimentações.
4. **Testes** — usuário com uma unidade vs administrador.

## Critério de conclusão

- Dashboard mostra contagem correta de clientes apenas da unidade vinculada.
- Administrador vê todas as unidades.

## Riscos

- Performance com muitas unidades — mitigar depois com cache se necessário.
