# ADR-0144: Override de saída física na captação por loja

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Tela pedidos-por-loja (`/admin/captacao/lotes/{lote}/pedidos-por-loja/{cliente}`)

## Contexto

O cadastro do cliente define a unidade padrão de saída física ([ADR-0143](ADR-0143-cliente-saida-fisico-unidade-negocio.md)). Na captação diária por loja, a operação precisa antecipar outra origem (ex.: venda saindo de um HUB específico) **só naquele lote**, sem alterar o cadastro.

## Decisão

- Na tela **pedidos por loja**, exibir opções: **galpão do faturamento do lote** + **cada unidade HUB ativa** (checkbox com exclusão mútua — apenas uma marcada).
- Gravar em `pedidos.id_unidade_negocio_saida_venda` via PATCH em tempo real; **não** alterar `clientes.id_unidade_negocio_saida_fisico_padrao`.
- Exibir o padrão do cadastro apenas como referência; badge «alterado neste lote» quando o pedido tiver override.
- Demais lotes/captações continuam usando o padrão do cadastro até haver override no pedido daquele lote.
- Na etapa «Saída estoque físico», opções permanecem galpão + HUB do lote ([ADR-0129](ADR-0129-saida-estoque-fisico-captacao.md)); override já gravado no pedido é respeitado (`garantirSaidaFisica` só preenche quando `null`).

## Alternativas consideradas

- Alterar cadastro do cliente ao mudar na captação — rejeitado; operação pediu escopo só do lote.
- Mesma lista de opções da etapa saída física (só galpão + HUB do lote) — rejeitado; na captação antecipada precisa escolher entre todos os HUBs.

## Consequências

- [PLAN-0144](../plans/PLAN-0144-saida-fisica-override-pedido-por-loja.md).
- Serviço `CaptacaoPedidoPorLojaSaidaFisicaService` centraliza opções e validação permitidas.
