# ADR-0072: Vínculo de frete após transferências do lote (Lucas)

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Pipeline Lucas ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)); fretes [ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md), [ADR-0003](../adr/0003-rateio-frete-compartilhado-entre-movimentacoes.md)

## Contexto

Após **Validar transferências**, o lote possui movimentações de transferência gerenciais (HUB→galpão). Nem toda transferência usa frete. O **frete** é cadastrado no módulo de logística **antes ou em paralelo** à captação de pedidos (status **ABERTO**). Lucas precisa de uma **tela dedicada** para vincular, por transferência do lote, um frete em aberto — ou seguir **sem frete**.

## Decisão

### Cadastro de frete (pré-requisito operacional)

- Fretes podem ser lançados **a qualquer momento** antes ou durante a captação; **não** bloqueiam pedidos.
- Somente fretes com situação **ABERTA** aparecem para vínculo ([ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md)).

### Posição no fluxo do lote

```text
TRANSFERENCIAS_GERENCIAIS_EFETIVADAS
  → [Lucas: tela Vincular frete do lote]
  → [Lucas: Concluir etapa de frete] (com ou sem vínculos)
TRANSFERENCIA_FINALIZADA   ← mesmo efeito de “Finalizar transferência” (romaneio travado)
```

- **Jefferson** só após `TRANSFERENCIA_FINALIZADA` (inalterado).

### Tela “Vincular frete — lote”

- Lista transferências **geradas na validação** daquele lote de galpão (`captacao_lote` ↔ `movimentacao_transferencia`).
- Por linha: origem, destino, fruta, quantidade; select de **frete ABERTO** (filtrado por escopo/unidade quando aplicável); ação **remover vínculo**.
- **Frete opcional:** linha pode permanecer sem `id_frete`; não impede conclusão da etapa.
- Reutilizar regra e serviço de [ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md) (`FreteRateioMovimentacaoService`, replay se necessário) em transferências `RECEBIDA_CONFORME` ([ADR-0065](ADR-0065-transferencia-sem-confirmacao-recebimento.md)).
- Botão **“Concluir etapa de frete”** no lote: exige que Lucas tenha **revisado** a lista (não exige frete em todas as linhas); status → `TRANSFERENCIA_FINALIZADA`; aplica travas de romaneio/preço da [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md).

### O que não muda

- Venda e rateio de frete na venda seguem fluxo existente; esta ADR cobre **transferência do lote de captação** apenas.

## Alternativas consideradas

- **Vincular frete na criação automática da transferência** — rejeitado; frete muitas vezes ainda não existe no momento da validação.
- **Obrigar frete em toda transferência** — rejeitado; operação confirmou que nem toda transferência tem frete.
- **Pular tela e vincular só na ficha da transferência** — rejeitado; Lucas precisa de visão consolidada do lote.

## Consequências

- Novo status intermediário `AGUARDANDO_VINCULO_FRETE` (ou subestado explícito entre gerencial efetivada e finalizada).
- [PLAN-0072](../plans/PLAN-0072-vinculo-frete-pos-transferencia-lote.md); integrar em [PLAN-0067](PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md).
- Permissão sugerida: `captacao.lote.vincular-frete` ou reutilizar `movimentacoes.transferencias.editar`.
