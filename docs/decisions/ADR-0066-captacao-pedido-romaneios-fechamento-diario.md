# ADR-0066: Captação de pedido por faturamento e galpão, rotas e romaneios

**Data:** 2026-05-23
**Status:** Aceito (atualizado)
**Contexto:** Alinhamento com PDF SB Controladoria; refinamento operacional (Atanásio/Jefferson/Lucas)

## Contexto

A captação de pedidos é organizada por **unidade de faturamento** (loja comercial que emite NF, ex.: Barbalha). Dentro de cada faturamento, cada **galpão operacional** ligado àquela unidade (ex.: Recife, Maceió, Paraíba — [ADR-0064](ADR-0064-galpoes-operacionais-venda-tres-eixos.md)) possui captação **própria e independente**.

Cada pedido pertence a uma **rota** (carro/percurso); a rota pode ser vinculada por outra pessoa a qualquer momento. São necessários dois romaneios por lote de galpão: **carregamento** (loja + rota) e **abastecimento** (fruta: estoque do galpão vs a receber). A geração de movimentações (transferência e venda) ocorre nas etapas posteriores — [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md).

## Decisão

### Escopo da captação

- Toda captação informa **`id_unidade_negocio_faturamento`** (unidade de faturamento) e **`id_unidade_negocio_galpao`** (galpão operacional onde a operação do dia está concentrada).
- Telas e lotes são filtrados por faturamento; dentro do faturamento, o operador trabalha **um galpão por vez**.
- Pedidos e romaneios de galpões diferentes **não se misturam** no mesmo lote.

### Pedido

- Entidade **Pedido** (+ itens), **sem** `movimentacoes` na captação.
- **Origem principal:** aplicativo móvel via API ([ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md)); **web** para visualização, edição e vínculo de rota no painel em tempo real.
- Campos mínimos: cliente, produto, quantidade, preço, data de entrega, **origem física** da fruta (para etapa de transferência), rota, faturamento, galpão.
- Precificação na captação: custo referência, preço/UM e margem — [ADR-0073](ADR-0073-captacao-app-custo-preco-margem-um.md).
- Travas de edição de quantidade/preço conforme status do lote ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)).

### Rota

- Cadastro de **Rotas** vinculadas ao **galpão** (e indiretamente ao faturamento daquele galpão).
- Pedido↔rota editável enquanto a captação do **faturamento/dia** não estiver finalizada ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md)).

### Romaneio 1 — carregamento (loja + rota)

- **Prévia em tempo real** enquanto `CAPTACAO_EM_ANDAMENTO`.
- Agrupa por **cliente/loja** e por **rota**; versão consolidada após [ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md).

### Finalização da captação (unidade de faturamento)

- Portão único: **“Finalizar captação”** pelo responsável da unidade de faturamento (Atanásio) — ver [ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md).
- **Não** existe “Captação concluída” por galpão liberando Lucas isoladamente.

### Romaneio 2 — abastecimento (consolidado na finalização do faturamento)

- Gerado na **finalização da captação do faturamento** (não manual, não por galpão isolado).
- Total por **fruta**; para cada fruta:
  1. **Do estoque do galpão** — atendido pelo saldo gerencial do galpão.
  2. **A receber** — demanda menos estoque; alimenta a lista que **Lucas** efetiva como transferência (origem física + galpão destino).

### Romaneio manual (sem pedidos)

- Reposição de estoque sem captação — [ADR-0074](ADR-0074-romaneio-manual-abastecimento-sem-captacao.md): só Romaneio 2 de abastecimento; mesmo galpão/faturamento; **sem** Romaneio 1.

### Fora desta ADR

- API app e painel web — [ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md).
- Finalização captação faturamento — [ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md).
- Pipeline Lucas/Jefferson — [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md).

## Alternativas consideradas

- **Captação única do dia sem separar faturamento/galpão** — rejeitada; operação trabalha por loja fiscal e por galpão físico.
- **Fechamento só por galpão liberando Lucas** — rejeitado; portão é por unidade de faturamento ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md)).
- **Romaneio 2 manual após botão separado** — rejeitado; consolidação automática na conclusão da captação reduz passo e erro.
- **Movimentação na captação** — rejeitado.

## Consequências

- Modelo de **lote de captação** por `(data, faturamento, galpão)` com status encadeado até vendas.
- Pacote índice: [PACOTE-0066](../pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md).
- [PLAN-0066](../plans/PLAN-0066-captacao-pedido-romaneios-fechamento-diario.md), [PLAN-0070](../plans/PLAN-0070-finalizar-captacao-unidade-faturamento.md), [PLAN-0067](../plans/PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md).
- Galpões do mesmo faturamento compartilham o mesmo portão de finalização; após [ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md), Lucas atua por galpão em `AGUARDANDO_TRANSFERENCIA_CIGAN`.
