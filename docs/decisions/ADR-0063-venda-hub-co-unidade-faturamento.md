# ADR-0063: Venda com saída no HUB — CO da unidade de faturamento

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Margem de venda com saída física no HUB (complementa ADR-0060)

## Contexto

Quando a fruta sai fisicamente do HUB para uma venda faturada por uma loja comercial (ex.: estoque em Quixeré, faturamento Fortaleza), o CO dessa loja ainda não está no preço médio do HUB. A ADR-0060 zerava o CO na margem nesse cenário, subestimando o custo comercial da praça.

## Decisão

Na venda com **saída física = HUB** (`id_unidade_negocio_estoque` aponta para unidade `is_hub`):

- Snapshot do **CO vigente da unidade de faturamento** (`id_empresa_origem` / `id_unidade_negocio_faturamento`) em `valor_custo_operacional` (R$/kg).
- O CO entra **somente na margem** (`resultado_movimentacao`), **sem** alterar preço médio do HUB nem da loja.
- Composição da margem: `valor_nf − valor_custo_saida (PM HUB) − (CO_faturamento × kg) − frete_rateio`.
- O HUB registra apenas a saída ao preço médio; lucro/prejuízo consolidado fica na unidade de faturamento via `resultado_movimentacao` e relatórios que filtram por origem/faturamento.

**Unidade de produção** com saída **não-HUB** mantém ADR-0050 (switch + CO do HUB selecionado). Saída comercial da própria loja mantém CO = 0 na margem (já embutido no PM).

Formulário manual: unidade de faturamento, saída física, cliente e frete opcional (já existentes; rótulos alinhados).

## Alternativas consideradas

- Manter CO = 0 na saída HUB (ADR-0060 original) — rejeitado; não reflete CO da praça comercial.
- Somar CO da loja em `valor_custo_saida` — rejeitado; contaminaria PM do estoque.

## Consequências

- ADR-0060: regra “CO na margem = 0 quando saída HUB” **substituída** por esta ADR para vendas com origem comercial.
- Testes de venda HUB+loja devem esperar CO da loja na margem.
- Importação de vendas continua sem saída HUB explícita (fora do escopo imediato).
