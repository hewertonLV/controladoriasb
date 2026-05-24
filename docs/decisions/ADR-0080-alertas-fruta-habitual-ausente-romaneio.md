# ADR-0080: Alertas — fruta habitual da loja ausente no romaneio em montagem

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Complemento a [ADR-0078](ADR-0078-alertas-lojas-sem-pedido-dia-semana.md); [PACOTE-0066](../pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md)

## Contexto

Além de saber **quais lojas não pediram** no dia ([ADR-0078](ADR-0078-alertas-lojas-sem-pedido-dia-semana.md)), a operação precisa saber **quais frutas** uma loja que **já pediu hoje** (ou está no romaneio 1 em montagem) costumava levar nas semanas anteriores no mesmo dia da semana, mas **ainda não constam** no pedido/romaneio da captação em curso — para cobrar item a item (ex.: “sempre leva banana e hoje só pediu maçã”).

## Decisão

### Pré-requisito

- Mesmo de [ADR-0078](ADR-0078-alertas-lojas-sem-pedido-dia-semana.md): **≥ 4 semanas** de histórico de **itens de pedido** no escopo faturamento/galpão.

### Escopo do cruzamento

- **Lote/romaneio em montagem:** captação do dia com status `CAPTACAO_EM_ANDAMENTO` ([ADR-0066](ADR-0066-captacao-pedido-romaneios-fechamento-diario.md)) — fonte = itens de pedido já gravados + prévia do Romaneio 1 (loja/rota).
- **Histórico:** itens de pedido das **últimas 4 ocorrências** do **mesmo dia da semana**, no mesmo escopo (faturamento e galpão).

### Regra (cliente × fruta)

Para cada **cliente que já possui pedido** no lote do dia (≥ 1 item na captação em andamento):

1. **Habitual por fruta:** produto/fruta presente em **≥ 2 das 4** últimas ocorrências daquele dia da semana nos pedidos da loja (mesmo limiar 2/4 de [ADR-0078](ADR-0078-alertas-lojas-sem-pedido-dia-semana.md)).
2. **Captado hoje:** conjunto de `id_produto` (ou fruta normalizada) nos itens do pedido da loja no lote em andamento.
3. **Alerta:** pares `(cliente, fruta)` em `habitual − captado hoje`.

Lojas **sem nenhum pedido** no dia **não** entram nesta tela (cobertas por [ADR-0078](ADR-0078-alertas-lojas-sem-pedido-dia-semana.md)).

### Tela

- Mesmo módulo **Alertas comerciais** que [ADR-0078](ADR-0078-alertas-lojas-sem-pedido-dia-semana.md): segunda aba/seção **“Frutas faltantes por loja”** (ex.: `/admin/captacao/alertas-comerciais#frutas`).
- Colunas sugeridas: loja, praça, fruta, qty média histórica (opcional), última vez que pediu naquele dia da semana.
- Filtros: data, faturamento, galpão; mesma permissão e escopo usuário↔UN.
- Atualização ao gravar pedido (web/app) — alinhado a [ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md).

### Fonte de dados

- Apenas **pedidos** do módulo novo; **não** usar vendas/import legado ([ADR-0079](ADR-0079-importacao-apenas-cadastro-sem-movimentacoes.md)).

### Fase

- Fase 2 do pacote; implementar junto ou imediatamente após [PLAN-0078](../plans/PLAN-0078-alertas-lojas-sem-pedido-dia-semana.md).

## Alternativas consideradas

- **Mesma lista da 0078 com coluna “frutas”** — rejeitado; regras e público distintos (loja sem pedido vs loja com pedido parcial).
- **Comparar com Romaneio 2 (abastecimento)** — rejeitado; alerta é **comercial na captação**, antes da finalização do faturamento.

## Consequências

- [PLAN-0080](../plans/PLAN-0080-alertas-fruta-habitual-ausente-romaneio.md).
- Query `FrutasHabituaisAusentesRomaneioQuery` com índice `(id_cliente, id_produto, data_referencia)`.
- Matriz de captação pode linkar da célula loja→alerta de faltantes (opcional, fase posterior).
