# ADR-0124: Adicionar estoque — múltiplas frutas e unidade fixa

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Tela manual de estoque a partir da unidade (ex.: HUB MV)

## Contexto

A operação abre o estoque da unidade e registra entradas no mesmo fluxo. O formulário antigo pedia unidade novamente, permitia saída e aceitava uma fruta por envio.

## Decisão

- Renomear fluxo para **Adicionar Estoque** (botão, título e card).
- Exigir `id_unidade_negocio` na URL; a unidade é exibida em leitura (sem select).
- Registrar apenas **entradas** (remover tipo saída nesta tela).
- Permitir **vários itens** por envio (`itens[]`: fruta, quantidade UM, preço UM), no padrão da Nova compra; conversão para kg via `kg_por_unidade_medicao` da fruta.
- Após salvar, redirecionar para a listagem da unidade com mensagem de sucesso.

## Alternativas consideradas

- Manter saída na mesma tela — rejeitado; o nome e o fluxo passam a ser só de entrada.
- Manter select de unidade — rejeitado; o usuário já escolheu a unidade antes.

## Consequências

- URL `/estoques/movimentar` sem unidade retorna 404.
- Request e testes passam a validar array `itens`.
