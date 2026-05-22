# ADR-0033: Dashboard Olho de Fabio — monitoramento em tempo real

**Data:** 2026-05-20
**Status:** Aceito (nome atualizado — ver [ADR-0039](ADR-0039-renomear-olho-de-fabio.md))
**Contexto:** Segunda dashboard para alertas de prejuízo sem degradar o sistema

## Contexto

A operação precisa ser avisada quando surgem vendas abaixo do custo, fretes elevados, rentabilidade negativa e outros cenários de perda, com atualização automática na tela.

## Decisão

- **Rota dedicada** `/olho-de-fabio`, permissão `olho-de-fabio.visualizar` (redirects legados de `/olho-de-deus` — ADR-0039).
- **Atualização:** polling HTTP incremental (não WebSocket/broadcast global); intervalo padrão 45s, configurável.
- **Isolamento de carga:** consulta só roda quando o usuário chama `GET /olho-de-fabio/poll`; throttle 4 req/min; aba oculta pausa o timer no cliente; limite de 25 movimentações e 50 alertas por poll.
- **Escopo:** mesmas unidades permitidas via `UnidadeNegocioAccessService`.
- **Cursor `since`:** cliente envia timestamp da última resposta; servidor busca movimentações com `created_at` ou `updated_at` posteriores.
- **Alertas iniciais:**
  - Preço NF/kg ou NF/UM de venda &lt; custo médio na saída
  - Frete/kg &gt; R$ 0,50
  - `resultado_movimentacao` &lt; 0
  - NF total &lt; custo de saída
  - Rentabilidade da loja (cliente/unidade/fruta) negativa no mês (via `RentabilidadeLojaService`)
  - Devolução com `resultado_devolucao` &lt; 0
  - Descarte/doação com valor ≥ R$ 500 (configurável)

## Alternativas consideradas

- Laravel Echo/Reverb — rejeitado: infraestrutura extra e broadcast para todos os workers.
- Polling a cada 5s — rejeitado: risco de carga desnecessária no MySQL.

## Consequências

- Usuários sem permissão não disparam consultas.
- Rentabilidade de loja por alerta pode executar relatório parcial; mitigado pelo limite de movimentações por poll.
