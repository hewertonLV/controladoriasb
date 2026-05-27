# ADR-0067: Pipeline Cigan e gerencial — Lucas (transferência) e Jefferson (venda)

**Data:** 2026-05-23
**Status:** Aceito (atualizado)
**Contexto:** Pós-captação ([ADR-0066](ADR-0066-captacao-pedido-romaneios-fechamento-diario.md)); arquivos Cigan com layout a definir

## Contexto

Após romaneio consolidado — captação finalizada por **Atanásio** ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md)) **ou** romaneio **manual** confirmado ([ADR-0074](ADR-0074-romaneio-manual-abastecimento-sem-captacao.md)) — **Lucas** opera primeiro no **Cigan** (transferência fiscal/oficial) e depois no SB (transferência gerencial HUB→galpão). **Jefferson** opera no Cigan (vendas) e só então o SB **efetiva** as movimentações de venda. Entre as etapas, regras distintas de **trava** do romaneio e dos **preços** se aplicam.

## Decisão

### Máquina de estados do lote

```text
[Fluxo A — com pedidos]
CAPTACAO_EM_ANDAMENTO
  → [Atanásio: Finalizar captação — ADR-0070] → romaneios 1 e 2 consolidados

[Fluxo B — sem pedidos, reposição estoque]
ROMANEIO_MANUAL_EM_EDICAO
  → [Confirmar romaneio manual — ADR-0074] → Romaneio 2 consolidado

AGUARDANDO_TRANSFERENCIA_CIGAN   ← Lucas (fluxos A e B)

  → [Lucas: Iniciar transferência] → download arquivo Cigan (layout futuro)
       • trava QUANTIDADES do romaneio (pedidos/itens/romaneio 2)
       • preços de venda continuam editáveis (app/web)
TRANSFERENCIA_CIGAN_INICIADA

  → (Lucas importa e efetiva no Cigan — fora do sistema)

  → [Lucas: Validar transferências] → SB cria transferências gerenciais HUB→galpão
       • quantidades = parcela "a receber" do romaneio (origem/destino informados)
       • preços ainda editáveis
TRANSFERENCIAS_GERENCIAIS_EFETIVADAS

  → [Lucas: Tela vincular frete do lote — ADR-0072] → frete ABERTO opcional por transferência
AGUARDANDO_VINCULO_FRETE

  → [Lucas: Concluir etapa de frete] → romaneio principal imutável (com ou sem frete nas linhas)
       • trava preços e quantidades do lote principal
       • permite apenas romaneio complementar (mesmo dia/galpão)
TRANSFERENCIA_FINALIZADA

  → [Jefferson: Iniciar faturamento] → download arquivo Cigan de vendas (layout futuro)
FATURAMENTO_CIGAN_INICIADO

  → (Jefferson importa e efetiva no Cigan — fora do sistema)

  → [Jefferson: Finalizar venda] → SB cria/efetiva movimentações de venda do lote
VENDAS_FINALIZADAS
```

### Travas de edição

| Campo / artefato | Até captação concluída | Após Iniciar transferência (Lucas) | Após Finalizar transferência (Lucas) | Após Finalizar venda (Jefferson) |
|------------------|------------------------|--------------------------------------|----------------------------------------|----------------------------------|
| Quantidades (pedido/romaneio) | Editável | **Travado** | Travado | Travado |
| Preço de venda | Editável | Editável | **Travado** (lote principal) | Travado |
| Romaneio principal | Recalcula na captação | Qtd congelada; preço pode mudar | **Imutável** | Imutável |

### Lucas — transferência

- Pré-requisito: `captacao_faturamento_dia` = `CAPTACAO_FATURAMENTO_FINALIZADA` para o faturamento do lote ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md)).

1. **Iniciar transferência:** gera **arquivo para download** (formato Cigan a especificar depois); status → `TRANSFERENCIA_CIGAN_INICIADA`; congela quantidades.
2. **Validar transferências:** após conferência/Cigan; sistema executa transferências internas ([ADR-0065](ADR-0065-transferencia-sem-confirmacao-recebimento.md)) origem (HUB etc.) → galpão destino; status → `TRANSFERENCIAS_GERENCIAIS_EFETIVADAS` → redireciona etapa de frete ([ADR-0072](ADR-0072-vinculo-frete-pos-transferencia-lote.md)).
3. **Vincular frete (tela do lote):** lista transferências do lote; vínculo opcional a frete **ABERTO** (cadastrado antes ou em paralelo à captação); reutiliza [ADR-0041](ADR-0041-vincular-frete-transferencia-recebida-conforme.md).
4. **Concluir etapa de frete:** não exige frete em todas as linhas; status → `TRANSFERENCIA_FINALIZADA`; bloqueia romaneio principal.

### Romaneio complementar

- Somente após **Finalizar transferência** do lote principal.
- Novo lote filho ou registro `romaneio_complementar` no mesmo `(data, faturamento, galpão)` com quantidades **adicionais** a transferir.
- Romaneio 2 do complementar reflete **somente** totais do complementar; nova rodada: Iniciar transferência → Validar → Finalizar (mesma máquina de estados, escopo reduzido).
- Não altera quantidades já congeladas do romaneio principal.

### Jefferson — venda

1. Somente lotes `CAPTACAO_PEDIDOS` em `TRANSFERENCIA_FINALIZADA` (estoque do galpão deve cobrir pedidos; validar saldo). Lotes `ROMANEIO_MANUAL` **não** têm etapa Jefferson ([ADR-0074](ADR-0074-romaneio-manual-abastecimento-sem-captacao.md)).
2. **Iniciar faturamento:** download **arquivo de vendas** para Cigan (layout futuro); status → `FATURAMENTO_CIGAN_INICIADO`.
3. **Finalizar venda:** cria/efetiva **movimentações de venda** para os pedidos do lote ([ADR-0060](ADR-0060-venda-origem-comercial-saida-fisica-hub.md), [ADR-0064](ADR-0064-galpoes-operacionais-venda-tres-eixos.md)); usa preços **vigentes no momento** (última edição antes da trava de preço em Finalizar transferência); status → `VENDAS_FINALIZADAS`.

**Importante:** movimentações de venda **não** são geradas em “Iniciar faturamento”; apenas em **Finalizar venda**, após o Cigan. Calendário típico: captação **D**, faturamento **D+1** ou na data de saída — [ADR-0076](ADR-0076-calendario-captacao-d0-faturamento-d1.md).

**Transferência gerencial:** ao **Validar transferências**, criação automática no SB — [ADR-0075](ADR-0075-transferencia-gerencial-lucas-escopo-unidade.md). Lucas enxerga romaneios das UN de faturamento **vinculadas** ao usuário.

### Arquivos Cigan

- Dois tipos por lote: **transferência** (Lucas, ao iniciar) e **vendas** (Jefferson, ao iniciar faturamento).
- Layout e colunas: **documentação futura**; MVP gera arquivo placeholder ou estrutura extensível até especificação.
- Download via rota autenticada; registrar `exported_at` e versão do snapshot de quantidades/preços usado na geração.

## Alternativas consideradas

- **Transferência gerencial antes do Cigan** — rejeitado; operação exige Cigan oficial primeiro.
- **Gerar vendas ao iniciar faturamento** — rejeitado; movimentações só em Finalizar venda.
- **Editar quantidades após Iniciar transferência** — rejeitado; alinha arquivo Cigan ao romaneio.
- **Romaneio complementar editando o principal** — rejeitado; apenas aditivo.

## Consequências

- Serviços: `GerarArquivoCiganTransferenciaLote`, `EfetivarTransferenciasGerenciaisLote`, `GerarArquivoCiganVendasLote`, `FinalizarVendasLote`.
- Permissões: `lote.transferencia.iniciar`, `.validar`, `.finalizar`, `lote.faturamento.iniciar`, `.finalizar`.
- [PLAN-0067](../plans/PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md) atualizado; depende de [PLAN-0068](PLAN-0068-api-pedidos-painel-tempo-real.md) para entrada app.
- ADR futura: especificação binária/layout dos arquivos Cigan.
