# ADR-0060: Venda — origem comercial, saída física e realocação HUB

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Venda direta do HUB com NF/rentabilidade da loja; ADR-0059 revertida

## Contexto

A operação fatura pela loja comercial (coluna B da planilha), mas a fruta sai fisicamente do HUB. CO na venda não deve duplicar o já embutido no preço médio do estoque. Quando o cadastro transferiu HUB→loja sem saída física, é preciso realocar saldo antes da venda.

## Decisão

- **`id_empresa_origem`** = origem comercial (loja, nunca HUB) — relatórios, ICMS e faturamento.
- **`id_unidade_negocio_estoque`** (nullable) = unidade de saída física; default = origem comercial.
- **`id_unidade_negocio_faturamento`** = sempre igual à origem comercial (sem select separado).
- **CO na margem da venda = 0** quando a saída física for HUB (`is_hub = true`). **Atualização:** substituído por [ADR-0063](ADR-0063-venda-hub-co-unidade-faturamento.md) — CO da unidade de faturamento na margem, sem alterar PM.
- **Realocação automática** na confirmação da venda: se saída física = HUB, **sempre** realocar a quantidade integral da venda da loja comercial via transferências HUB→loja **RECEBIDA_CONFORME** (última elegível primeiro), estornando ao preço de entrada original da transferência (desfaz efeito no custo médio da loja), devolver ao HUB ao preço de saída original **sem CO do HUB**, e dar replay nas duas unidades. Ver [ADR-0061](ADR-0061-realocacao-hub-venda-sempre-loja-faturamento.md).
- **ADR-0059** substituída: HUB não aparece como origem comercial; importação usa origem comercial + select de saída física.

## Alternativas consideradas

- **Origem = HUB + faturamento separado (ADR-0059)** — rejeitado; HUB é centro de distribuição, não faturamento.
- **Realocação manual em etapa separada** — rejeitado; operação pediu automático na confirmação.
- **Somar CO do HUB na entrada da realocação** — rejeitado; CO já entrou quando a fruta chegou ao HUB.

## Consequências

- Migration `id_unidade_negocio_estoque` em `movimentacoes`.
- Novo `RealocacaoEstoqueHubVendaService`.
- UI manual e importação com dois selects (comercial + físico).
- Cancelamento/replay de venda usa unidade de estoque efetiva.
