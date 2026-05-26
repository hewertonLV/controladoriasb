# ADR-0114: Código material Cigan só das frutas do lote

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** TXT transferência ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md), [ADR-0111](ADR-0111-cigan-edi-codigo-material-unidade-negocio.md))

## Contexto

O gerador buscava `Fruta` com `whereIn` global a partir do romaneio. Se a relação `pedidos` do lote estivesse carregada de outro contexto, ou o romaneio não refletisse só os pedidos do lote, o `id_cigam` no TXT podia não corresponder à captação do dia.

## Decisão

- `RomaneioAbastecimentoService` carrega pedidos com `where('id_captacao_lote', $lote->id)` e inclui `id_cigam` em cada linha.
- `CiganEdiNfTransferenciaGerador` recarrega o lote (`findOrFail`), limpa `pedidos` e monta o material **somente** com `id_cigam` das linhas do romaneio daquele lote (pedidos + romaneio manual).

## Alternativas consideradas

- Manter `Fruta::whereIn` — rejeitado: desacoplado dos pedidos do lote e sujeito a relação já carregada.
- Snapshot de `id_cigam` no item do pedido — rejeitado neste passo; cadastro global da fruta continua fonte.

## Consequências

- [PLAN-0114](../plans/PLAN-0114-cigan-edi-material-fruta-do-lote.md).
- Material no TXT = `id_cigam` das frutas captadas no lote, não de outro lote.
