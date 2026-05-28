# PLAN-0145: Custo na captação por loja conforme saída física

**ADR:** [ADR-0145](../decisions/ADR-0145-custo-pedido-por-loja-saida-fisica.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Exibir e persistir custo de referência pelo PM da unidade de saída + CO do faturamento quando saída for HUB.

## Passos

1. `custoReferenciaPorUmNaSaidaFisica` em `CaptacaoPrecificacaoService`.
2. `PedidoService` — resolver custo nos itens e recalcular ao mudar saída.
3. Controller/view — exibição e atualização AJAX dos custos.
4. Testes unitários e feature.

## Critério de conclusão

Trocar saída para HUB com estoque e CO cadastrado altera custo na tela e no item salvo.

## Riscos

- HUB sem estoque da fruta mostra `s/ est.` mesmo com galpão com saldo — operação deve escolher unidade com estoque.
