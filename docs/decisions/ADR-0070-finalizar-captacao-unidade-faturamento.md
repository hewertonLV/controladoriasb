# ADR-0070: Finalizar captação da unidade de faturamento

**Data:** 2026-05-23
**Status:** Aceito (atualizado)
**Contexto:** Pacote captação ([PACOTE-0066](../pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md)); libera etapa Lucas

## Contexto

Os pedidos chegam ao longo do dia por app e matriz web, por **galpão**, sob uma **unidade de faturamento** (ex.: Barbalha). Só depois que a operação considera que **todos os pedidos do dia chegaram** é que deve ser possível iniciar transferências no Cigan (Lucas).

## Decisão

- Entidade de controle **`captacao_faturamento_dia`** (ou equivalente): chave `(data_referencia, id_unidade_faturamento)` com status próprio.
- Enquanto **não** finalizado: galpões permanecem em `CAPTACAO_EM_ANDAMENTO`; pedidos e rotas **editáveis** (app/web); romaneios 1 e 2 em **prévia** (recalculam em tempo real).
- Ação **“Finalizar captação”** — qualquer usuário que cumpra **ambos**:
  - **Vínculo** com a unidade de negócio de **faturamento** do lote (ex.: Barbalha, Fortaleza);
  - Permissão `captacao.faturamento.finalizar` (ou equivalente no enum):
  - Valida todos os lotes de galpão daquele faturamento na data (pedidos sem rota → bloquear ou exceção).
  - Para **cada galpão** com pedidos: consolida Romaneio 1 e Romaneio 2; congela **inclusão de novos pedidos** e **alteração de rotas/quantidades** na captação (preços seguem editáveis até Lucas iniciar transferência — [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)).
  - Status dos lotes de galpão → `AGUARDANDO_TRANSFERENCIA_CIGAN`.
  - Status do faturamento/dia → `CAPTACAO_FATURAMENTO_FINALIZADA`.
- **Lucas** só vê lotes e o botão **“Iniciar transferência”** após `CAPTACAO_FATURAMENTO_FINALIZADA` do faturamento correspondente.
- Reabrir captação do dia (correção) fica **fora do MVP** — ADR futura se necessário.

## Alternativas consideradas

- **Cada galpão concluir e liberar Lucas isoladamente** — rejeitado; operação exige fechamento único por faturamento antes do Cigan.
- **Lucas visível antes do fechamento** — rejeitado; risco de transferir com pedidos ainda entrando.
- **Finalizar por galpão sem faturamento** — rejeitado; fecha a unidade fiscal inteira.
- **Restringir a um papel fixo (ex.: só Atanásio)** — rejeitado; usa vínculo UN + permissão.

## Consequências

- [ADR-0066](ADR-0066-captacao-pedido-romaneios-fechamento-diario.md) deixa de usar “Captação concluída” por galpão como portão para Lucas.
- [PLAN-0070](../plans/PLAN-0070-finalizar-captacao-unidade-faturamento.md); integrar em PLAN-0066 e PLAN-0067.
- Dashboard: faturamento “Captação aberta” vs “Finalizada — aguardando Lucas”.
