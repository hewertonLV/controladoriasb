# ADR-0141: Custo operacional — snapshot na data da movimentação

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** Recálculos, devoluções e correções retroativas; [ADR-0135](ADR-0135-venda-hub-co-faturamento-embutido-custo-saida.md)

## Contexto

O CO da unidade muda ao longo do tempo em `historico_c_o_un_ng`. Reprocessar estoque, corrigir custos ou registrar devolução usando o CO **vigente hoje** distorce movimentações passadas.

## Decisão

1. Toda movimentação que usa CO grava **`id_custo_operacional`** e **`valor_custo_operacional`** (R$/kg) no momento do registro, referente ao CO vigente na **data da operação** (`data_movimentacao` / emissão).
2. **Replay e margem** usam os campos da própria movimentação, não o histórico atual.
3. **Devolução** de venda com saída HUB retorna à loja de faturamento reutiliza o CO **da venda origem**, não o vigente hoje.
4. **Correção retroativa** (ex.: `movimentacoes:corrigir-vendas-hub-co`) recalcula PM/custo de saída mas **preserva** o CO já gravado na venda; só preenche CO ausente via histórico na data da venda.
5. Consulta histórica: último `historico_c_o_un_ng` com `created_at <= data_referencia` da unidade.

## Alternativas consideradas

- **Sempre CO vigente** — rejeitado; invalida auditoria temporal.
- **Só valor sem id_custo_operacional** — rejeitado; perde rastreio do registro de histórico usado.

## Consequências

- Helper `CustoOperacionalSnapshot` centraliza leitura na data e snapshot da movimentação.
- Compras e transferências passam a resolver CO na `data_movimentacao` do lançamento.
