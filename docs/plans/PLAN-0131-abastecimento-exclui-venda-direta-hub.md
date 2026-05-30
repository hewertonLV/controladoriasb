# PLAN-0131: Abastecimento galpão exclui loja com saída física no HUB

**ADR:** [ADR-0131](../decisions/ADR-0131-abastecimento-exclui-venda-direta-hub.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Transferência HUB→galpão e romaneio «a receber» consideram só lojas com saída no galpão; validação de estoque no HUB cobre transferência + venda direta do HUB.

## Passos

1. **RomaneioAbastecimentoService** — filtrar demanda por `id_unidade_negocio_saida_venda`; expor necessidade HUB.
2. **ValidarEstoqueHubNfTransferenciaCiganService** — usar necessidade HUB total.
3. **ConcluirSaidaEstoqueFisico** — revalidar estoque HUB antes das transferências.
4. **Testes** — romaneio, integração transferência mista.

## Critério de conclusão

Loja com saída HUB: `a_receber` = 0 para suas quantidades; sem transferência; venda debita HUB.

## Riscos

- NF enviada antes de marcar HUB — mitigação: revalidação ao concluir saída física.
