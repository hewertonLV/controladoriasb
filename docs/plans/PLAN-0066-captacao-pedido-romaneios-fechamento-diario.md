# PLAN-0066: Captação por faturamento e galpão, rotas e romaneios

**ADR:** [ADR-0066](../decisions/ADR-0066-captacao-pedido-romaneios-fechamento-diario.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Entregar captação de pedidos por unidade de faturamento e galpão, romaneios de carregamento e abastecimento (consolidados ao concluir a captação do galpão), sem movimentações de estoque.

## Pré-requisitos

- [PLAN-0064](../plans/PLAN-0064-galpoes-operacionais-venda-tres-eixos.md) estável (galpões vinculados ao faturamento).
- Clientes, praças, frutas, unidades de negócio cadastrados.

## Passos

1. **Lote de captação** — entidade `captacao_lotes` (ou equivalente): `data`, `id_unidade_faturamento`, `id_unidade_galpao`, status inicial `CAPTACAO_EM_ANDAMENTO`.
2. **Rotas** — CRUD por galpão; veículo opcional.
3. **Pedidos** — CRUD dentro do lote; faturamento + galpão herdados do lote; origem física por item; sem `movimentacoes`.
4. **Vínculo de rota** — edição livre até conclusão do lote; bloqueio de conclusão sem rota.
5. **Romaneio 1** — preview ao vivo por loja e rota dentro do lote.
6. **Romaneios em prévia** — recálculo ao vivo durante `CAPTACAO_EM_ANDAMENTO`.
7. **Integração ADR-0070** — ver [PLAN-0070](PLAN-0070-finalizar-captacao-unidade-faturamento.md) (botão Atanásio + consolidação).
8. **Permissões e testes** — captação por escopo de faturamento/galpão; romaneio prévia; sem liberar Lucas neste plano.

## Critério de conclusão

- Cada galpão tem captação isolada sob seu faturamento.
- “Finalizar captação” (PLAN-0070) consolida romaneios e libera Lucas ([PLAN-0067](PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md)).
- Nenhuma transferência ou venda é criada neste plano.

## Riscos

- Vários galpões do mesmo faturamento em status diferentes — mitigar dashboard de lotes por status.
- Origem física incorreta no pedido — validar UN de origem elegível (HUB, produção, etc.) na digitação.

## Dependência

- [PLAN-0068](PLAN-0068-api-pedidos-painel-tempo-real.md) em paralelo após passo 3.
- [PLAN-0070](PLAN-0070-finalizar-captacao-unidade-faturamento.md) após passos 5–6.
- [PLAN-0067](PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md) após PLAN-0070.
