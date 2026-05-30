# PLAN-0141: Custo operacional — snapshot na data da movimentação

**ADR:** [ADR-0141](../decisions/ADR-0141-custo-operacional-snapshot-data-movimentacao.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Garantir que CO em movimentações, devoluções e recálculos use o valor da operação, não o vigente após mudanças no cadastro.

## Passos

1. Criar `CustoOperacionalSnapshot` (vigente na data + leitura da movimentação).
2. Devolução HUB→loja: CO da venda origem.
3. Venda: snapshot na data de emissão; correção hub preserva CO gravado.
4. Compra/transferência: CO na `data_movimentacao`.
5. Teste devolução com CO alterado após a venda.

## Critério de conclusão

- Testes de devolução e vendas verdes; novo teste de snapshot temporal passa.

## Riscos

- Histórico sem linha anterior à data — mitigação: fallback no `custo_operacional` da unidade.
