# ADR-0142: Padrão de saída estoque físico no cadastro do cliente

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Etapa «Saída estoque físico (SB Controladoria)» da captação ([ADR-0129](ADR-0129-saida-estoque-fisico-captacao.md))

## Contexto

Na aba de saída física, cada loja escolhe entre o galpão da unidade de faturamento do lote e o HUB da transferência Cigam. O padrão era sempre o galpão. A operação precisa que lojas com saída habitual no HUB já entrem com essa opção marcada.

## Decisão

- Campo obrigatório no cadastro do cliente: `saida_estoque_fisico_padrao` (`galpao` | `hub`), default `galpao`.
- Na aba «Saída estoque físico», opções permitidas permanecem **galpão do lote** e **HUB do lote** (`id_unidade_negocio_hub_origem`).
- Pré-seleção por loja: valor do cadastro do cliente, resolvido para o ID da unidade do lote (se cliente prefere HUB mas o lote não tem HUB, cai no galpão).
- Ao enviar a NF de transferência Cigam, pedidos com quantidade captada e `id_unidade_negocio_saida_venda` nulo recebem o padrão do cliente persistido.
- Pedidos novos na matriz **não** gravam saída até o upload da NF ou escolha manual na aba.

## Alternativas consideradas

- Padrão global por carteira — rejeitado; varia por loja.
- Incluir unidades além de galpão/HUB do lote — rejeitado; mantém escopo da ADR-0129.

## Consequências

- [PLAN-0142](../plans/PLAN-0142-saida-estoque-fisico-padrao-cliente.md).
- ADR-0129 permanece válida para pipeline e opções; o default deixa de ser fixo «galpão» e passa a ser por cliente.
