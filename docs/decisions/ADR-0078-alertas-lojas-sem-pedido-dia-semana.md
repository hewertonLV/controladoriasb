# ADR-0078: Alertas — lojas sem pedido no dia da semana habitual

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** PDF §17 (alertas comerciais); [PACOTE-0066](../pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md)

## Contexto

Com histórico de captação, a operação quer identificar **lojas que costumam pedir em determinado dia da semana** mas **ainda não pediram** no dia de captação em curso — apoio comercial (ex.: cobrar cliente na terça se sempre pede às terças).

## Decisão

### Pré-requisito

- Feature disponível quando existir **pelo menos 4 semanas** de histórico de pedidos (itens com `data_referencia` ou data do lote) no escopo da unidade de faturamento/galpão.

### Regra

- Para **hoje** (dia da semana *D*) e escopo selecionado (faturamento e/ou galpão):
  1. Calcular conjunto **habitual:** clientes/lojas com **≥ 1 pedido** em pelo menos **2 das últimas 4 ocorrências** do mesmo dia da semana *D* (ou limiar configurável: 2/4 semanas).
  2. Calcular conjunto **captado hoje:** clientes com pedido no lote de captação **em aberto** do dia.
  3. **Alerta:** `habitual − captado hoje` = lojas que **ainda não pediram** mas costumam pedir neste dia.

### Tela

- Módulo **Alertas comerciais** — aba **“Lojas sem pedido”** (ex.: `/admin/captacao/alertas-comerciais`). Aba complementar **frutas habituais ausentes** por loja que já pediu: [ADR-0080](ADR-0080-alertas-fruta-habitual-ausente-romaneio.md).
- Filtros: data, unidade de faturamento, galpão; lista com nome da loja, praça, último pedido naquele dia da semana, contato opcional.
- Atualização: recalcular ao abrir e opcionalmente via broadcast quando novo pedido entra ([ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md)).
- Permissão dedicada; escopo por vínculo usuário↔UN.

### Fora do MVP inicial do pacote

- Implementar **após** módulo de pedidos em produção e 4 semanas de dados; não bloqueia PLAN-0066–0077.

## Alternativas consideradas

- **Alertar todas as lojas sem pedido** — rejeitado; ruído alto sem padrão semanal.
- **Usar vendas históricas importadas** — rejeitado para alerta; fonte = **pedidos** do novo módulo.

## Consequências

- [PLAN-0078](../plans/PLAN-0078-alertas-lojas-sem-pedido-dia-semana.md); fase 2 do pacote.
- Job ou query `LojasSemPedidoDiaSemanaQuery` com índice em `(id_cliente, data)`.
