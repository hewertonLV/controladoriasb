# PLAN-0144: Override de saída física na captação por loja

**ADR:** [ADR-0144](../decisions/ADR-0144-saida-fisica-override-pedido-por-loja.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Permitir escolher a saída física por loja na tela pedidos-por-loja, persistindo só no pedido do lote.

## Pré-requisitos

- ADR-0143 (padrão no cliente) e coluna `pedidos.id_unidade_negocio_saida_venda`.

## Passos

1. **Serviço** — `CaptacaoPedidoPorLojaSaidaFisicaService` com opções e validação.
2. **Domínio** — `PedidoService::atualizarSaidaFisicaVendaPedidoPorLoja`.
3. **HTTP** — rota PATCH, request, controller.
4. **UI** — checkboxes exclusivos + AJAX na view show.
5. **Testes** — feature em `CaptacaoPedidoPorLojaTest`.

## Critério de conclusão

- Usuário altera saída na tela pedidos-por-loja; cadastro do cliente inalterado; outro lote usa padrão do cadastro.

## Riscos

- Opção HUB escolhida na captação pode não coincidir com `hub_origem` do lote na etapa Cigam — operação deve alinhar manualmente.
