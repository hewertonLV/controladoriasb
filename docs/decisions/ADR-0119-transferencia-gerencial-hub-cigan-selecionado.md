# ADR-0119: Transferência gerencial usa o HUB da aba Arquivo Cigan

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Upload da NF ([ADR-0118](ADR-0118-upload-nf-transferencia-cigan.md), [ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

O upload da NF dispara `EfetivarTransferenciasGerenciaisLoteService`, que cria movimentações de transferência (saída HUB → entrada galpão do lote). A origem era resolvida por `id_unidade_origem_fisica` do pedido ou pelo primeiro HUB do cadastro, ignorando `captacao_lotes.id_unidade_negocio_hub_origem` escolhido para o TXT Cigan.

## Decisão

- Para lotes **Captação pedidos**, a origem de **todas** as transferências gerenciais geradas no upload (ou validação) é **`id_unidade_negocio_hub_origem`** do lote, obrigatório antes do envio da NF.
- Destino: empresa do **galpão** do lote (`id_unidade_negocio_galpao`).
- Quantidades: parcela **a receber** do Romaneio 2 ([ADR-0075](ADR-0075-transferencia-gerencial-lucas-escopo-unidade.md)).
- Romaneio manual mantém origem por linha (`id_unidade_origem_fisica`); não exige HUB Cigan no lote.

## Alternativas consideradas

- Manter resolução por item do pedido — rejeitado; diverge do HUB usado no arquivo fiscal Cigan.
- Origem fixa no primeiro HUB do sistema — rejeitado; operação escolhe o HUB na matriz.

## Consequências

- [PLAN-0119](../plans/PLAN-0119-transferencia-gerencial-hub-cigan-selecionado.md).
- Upload sem HUB salvo retorna erro de validação.
