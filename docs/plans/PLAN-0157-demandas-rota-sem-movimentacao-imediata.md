# PLAN-0157: Demandas na conclusão da rota sem movimentação imediata

**ADR:** [ADR-0157](../decisions/ADR-0157-demandas-rota-sem-movimentacao-imediata.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Concluir rota da captação registrando demandas de transferência/venda sem movimentar estoque no SB.

## Pré-requisitos

- Migrations `captacao_lote_movimentacoes` com colunas de rota/pedido ([PLAN-0154](PLAN-0154-transferencia-venda-pendente-conclusao-rota.md) parcialmente aplicado).

## Passos

1. **Refatorar serviço** — `EfetivarDemandasMovimentacaoRotaCaptacaoService`: remover chamadas a `TransferenciaMovimentacaoService::criarTransferenciaAguardandoRecebimento` e geração imediata de movimentações de venda; persistir apenas `CaptacaoLoteMovimentacao` + `VendaNota` em `ABERTO`.
2. **Demanda automática transferência** — manter agregação `(lote, rota, origem, fruta)` só quando `pedidoExigeTransferenciaParaGalpao`; sem validação de estoque.
3. **Demanda venda** — `GerarVendasCaptacaoLoteService::gerarVendaPedidoNaConclusaoRota` criar nota/demanda sem `registrarVenda` imediato para galpão; remover caminho `aguardandoTransferencia` bloqueante.
4. **Testes** — concluir rota com estoque zero não falha; demandas criadas com status `ABERTO`; sem linhas em `movimentacoes` novas.

## Critério de conclusão

- Rota 1 (lote real) conclui sem erro SQL/estoque; cards de demanda aparecem; estoque inalterado até ações posteriores.

## Riscos

- Código legado ADR-0154 ainda acoplado — revisar `EfetivarVendasPendentesCaptacaoRotaService` e confirmar recebimento.
