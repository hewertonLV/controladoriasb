# ADR-0074: Romaneio manual de abastecimento (sem captação de pedidos)

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Reposição de estoque / solicitação de fruta sem pedidos de clientes ([PACOTE-0066](../pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md))

## Contexto

Às vezes a operação solicita fruta **sem passar pela captação de pedidos**, apenas para manter o **estoque do galpão** abastecido. É necessário montar um **romaneio manual** com quantidades por fruta (origem física e galpão destino) para **Lucas** executar o mesmo pipeline de transferência (Cigan + gerencial + frete), **sem** pedidos, romaneio de carregamento por rota ou etapa Jefferson.

## Decisão

### Tipo de lote

- Campo `tipo_lote` no registro de lote operacional: `CAPTACAO_PEDIDOS` (padrão) | `ROMANEIO_MANUAL`.
- Lote `ROMANEIO_MANUAL`: `(data, faturamento, galpão destino)`; **zero** pedidos vinculados.

### Tela “Romaneio manual de abastecimento”

- CRUD de **linhas manuais**: fruta, quantidade (na UM da fruta), **origem física** (unidade de saída — HUB etc.), galpão destino = do lote.
- Motivo/observação opcional (ex.: “reposição estoque”).
- **Romaneio 1 (carregamento)** **não se aplica** — sem lojas/rotas.
- Ao **confirmar romaneio manual**: calcular **Romaneio 2** (mesma regra da captação):
  - por fruta: **do estoque do galpão** vs **a receber**;
  - linhas “a receber” alimentam transferências de Lucas.

### Portão antes de Lucas

- **Não** exige [ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md) (não há captação de pedidos).
- Ação **“Confirmar romaneio manual”** (permissão operacional/logística) → status `AGUARDANDO_TRANSFERENCIA_CIGAN`.
- Pode coexistir no mesmo dia com lotes de captação de pedidos do mesmo galpão (lotes **separados**).

### Pipeline Lucas ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md))

- Lote manual entra no **mesmo** fluxo a partir de `AGUARDANDO_TRANSFERENCIA_CIGAN`: Iniciar transferência → Cigan → Validar → Vincular frete → Concluir etapa de frete.
- Quantidades travadas ao **Iniciar transferência**; sem preços de venda a editar (não há pedidos).

### Jefferson / vendas

- Lote `ROMANEIO_MANUAL` **não** possui etapa Jefferson (`FATURAMENTO_CIGAN` / `VENDAS_FINALIZADAS`); encerra em `TRANSFERENCIA_FINALIZADA`. **Confirmado:** fluxo manual = **somente abastecimento/transferência**; sem pedidos, sem venda, sem Cigan de vendas.
- Romaneio **complementar** ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)) permanece vinculado a lotes com pedidos; manual avulso é tipo distinto.

## Alternativas consideradas

- **Incluir linhas manuais no romaneio complementar** — rejeitado; complementar pressupõe captação prévia; reposição sem pedido é caso próprio.
- **Transferência direta sem romaneio** — rejeitado; perde conferência estoque vs a receber e arquivo Cigan.
- **Exigir Atanásio finalizar captação** — rejeitado; não há pedidos no dia.

## Consequências

- [PLAN-0074](../plans/PLAN-0074-romaneio-manual-abastecimento-sem-captacao.md).
- [ADR-0066](ADR-0066-captacao-pedido-romaneios-fechamento-diario.md) e [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md) referenciam este tipo.
- Listagem Lucas unifica lotes `CAPTACAO_PEDIDOS` (pós-0070) e `ROMANEIO_MANUAL` (pós-confirmação manual).
