# ADR-0129: Saída estoque físico após transferência Cigam

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** Pipeline captação ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)); upload NF transferência

## Contexto

Após o HUB efetuar a transferência no Cigam e o operador enviar a NF no SB, era necessário executar imediatamente as transferências gerenciais HUB→galpão. A operação precisa antes definir, **por loja**, de qual estoque físico a venda irá debitar (galpão ou HUB usado no arquivo Cigam).

## Decisão

- Novo status `SAIDA_ESTOQUE_FISICO` (**Saída estoque físico (SB Controladoria)**) entre `TRANSFERENCIA_CIGAN_INICIADA` e `AGUARDANDO_VINCULO_FRETE`.
- **Upload da NF de transferência:** apenas armazena o arquivo e avança para `SAIDA_ESTOQUE_FISICO` — **sem** movimentações de transferência.
- **Aba «Saída estoque físico»:** tabela no formato do Romaneio 1 (carregamento) com coluna por loja: origem da saída na venda = **galpão** ou **HUB** (`id_unidade_negocio_hub_origem` do lote). Padrão: galpão. Gravação em tempo real (AJAX) em `pedidos.id_unidade_negocio_saida_venda`.
- **Sincronização entre usuários:** o endpoint `matriz.estado` (poll da matriz) inclui `id_unidade_negocio_saida_venda` por loja; a aba atualiza os rádios sem recarregar a página, sem alterar o campo em foco nem durante salvamento local.
- **Concluir saída estoque físico:** valida estoque no HUB, executa `EfetivarTransferenciasGerenciaisLoteService` (somente demanda de lojas com saída no galpão — [ADR-0131](ADR-0131-abastecimento-exclui-venda-direta-hub.md)) e vai para `AGUARDANDO_VINCULO_FRETE`.
- **Finalizar vendas:** `id_unidade_negocio_estoque` da movimentação = valor salvo por loja; `id_empresa_origem` / faturamento permanecem na unidade de faturamento do lote.

## Alternativas consideradas

- Escolha por fruta/linha — rejeitado; operação pediu por loja.
- Transferência no upload da NF — rejeitado; operação pediu etapa dedicada após conferir origem de saída.

## Consequências

- [PLAN-0129](../plans/PLAN-0129-saida-estoque-fisico-captacao.md).
- Romaneio manual sem pedidos/lojas **não** usa esta aba (mantém conclusão direta na transferência).
