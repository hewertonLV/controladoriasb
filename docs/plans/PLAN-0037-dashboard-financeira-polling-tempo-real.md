# PLAN-0037: Dashboard financeira — polling em tempo real

**ADR:** [ADR-0037](../decisions/ADR-0037-dashboard-financeira-polling-tempo-real.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Dashboard financeira atualiza cards e gráficos periodicamente sem refresh da página.

## Pré-requisitos

- ADR-0037 aceita
- Endpoint `GET /dashboard/dados` existente

## Passos

1. **Config** — `config/dashboard_financeiro.php` com intervalo e throttle.
2. **API** — incluir `proximo_poll_ms` na resposta JSON; throttle na rota.
3. **View** — badge de monitoramento e botões pausar/retomar.
4. **JS** — polling, visibilitychange, integração com filtros.

## Critério de conclusão

- Página aberta e visível dispara polls no intervalo configurado.
- Aba oculta pausa; retomar ou voltar à aba retoma.
- Testes de feature da dashboard passam.

## Riscos

- Carga no banco — mitigar com throttle e intervalo ≥ 45s.
