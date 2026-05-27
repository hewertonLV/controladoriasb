# ADR-0048: Importação de vendas (NF) por planilha

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Movimentação — Venda

## Contexto

Operação precisa registrar muitas linhas de NF de venda. O cadastro manual item a item é lento. O layout deve espelhar os dados fiscais/comerciais por linha de fruta.

## Decisão

Importação assíncrona (preview + confirmação), no padrão de transferências:

- **A** número da NF · **B** CNPJ origem (unidade) · **C** CPF/CNPJ cliente · **D** id CIGAM fruta · **E** quantidade · **F** unidade de medição · **G** valor total da linha.
- Duplicidade na planilha somente quando **todos** os sete campos coincidem (após normalização).
- Coluna F deve bater com a unidade de medição cadastrada da fruta.
- Confirmação agrupa linhas selecionadas com mesma NF + origem + cliente em uma `VendaNota` com vários itens (`VendaMovimentacaoService::registrarVenda`).
- Sem frete na importação; `data_emissao` = momento da confirmação (como venda manual).
- Origem HUB sem coluna de faturamento → erro no preview (cadastro manual).

Permissões: `movimentacoes.vendas.importar` e `movimentacoes.vendas.importar-confirmar`.

## Alternativas consideradas

- **Uma NF por linha sempre** — rejeitado: duplicaria notas quando várias frutas compartilham a mesma NF.
- **Duplicidade só por NF + fruta** — rejeitado: o pedido exige comparar quantidade, UM e valor também.

## Consequências

- Tabela `venda_importacoes`, fila `vendas-importacao`, botão na listagem de vendas.
- Origem sem estoque da fruta → erro (mesma regra prática das transferências).
