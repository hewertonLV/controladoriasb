# ADR-0037: Dashboard financeira — polling em tempo real

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Paridade com monitoramento da dashboard Olho de Fabio

## Contexto

A dashboard financeira atualizava só ao mudar switches (debounce) ou ao clicar Buscar, sem consultas periódicas enquanto a página permanecia aberta.

## Decisão

- Polling no cliente com o mesmo padrão do Olho de Fabio: `setTimeout`, intervalo em `config/dashboard_financeiro.php` (padrão 45s), pausa quando a aba está oculta, botões Pausar/Retomar.
- `GET /dashboard/dados` retorna `proximo_poll_ms` e throttle de 4 req/min.
- Ao carregar a página, aplica o payload SSR e inicia o monitoramento automaticamente; Buscar (mês) e switches disparam atualização imediata e reagendam o próximo poll.

## Alternativas consideradas

- WebSockets — complexidade e infraestrutura desnecessárias para o volume atual.
- Apenas debounce nos switches — não atende “tempo real” sem refresh manual.

## Consequências

- Mais requisições ao servidor enquanto a dashboard estiver aberta; mitigado por throttle e pausa com aba oculta.
- Dois badges de status na UI: unidades selecionadas e estado do monitoramento.
