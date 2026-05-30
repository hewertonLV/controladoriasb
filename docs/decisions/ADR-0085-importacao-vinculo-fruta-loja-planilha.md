# ADR-0085: Importação de vínculos fruta×loja por planilha

**Data:** 2026-05-25
**Status:** Aceito
**Contexto:** Tela Frutas por loja ([ADR-0071](ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md))

## Contexto

Operação precisa vincular muitas frutas a muitas lojas de uma vez. A tela manual com checkbox não escala para carga inicial ou atualização em massa.

## Decisão

- Planilha Excel com **coluna A** = `id_cigam` do cliente; **coluna B** = `id_cigam` da fruta (linha 1 = cabeçalho). Ver [ADR-0170](ADR-0170-importacao-vinculo-fruta-loja-id-cigam.md) (substitui layout por nome do MVP).
- Importação **aditiva**: cada linha válida **adiciona** o vínculo; vínculos já existentes vão para “já vinculados”; nenhuma fruta é removida da loja.
- Escopo restrito à **unidade de faturamento** selecionada na tela (mesmo filtro da listagem).
- Resolução da loja: `razao_social` ou `fantasia`, comparação sem acento e case-insensitive; ambiguidade (duas lojas com a mesma chave) → erro na linha.
- Resolução da fruta: `nome` no cadastro, mesma normalização; fruta inexistente → erro.
- Fluxo assíncrono padrão do sistema: upload → preview (novos vínculos / já vinculados / erros) → confirmar seleção.
- Permissão: `captacao.cliente_fruta.vincular` (mesma da edição manual de vínculos).

## Alternativas consideradas

- **Substituir todos os vínculos da loja pela planilha** — rejeitado; risco de apagar vínculos não listados.
- **Coluna com ID CIGAM da loja** — rejeitado no MVP; operação trabalha com nomes.
- **Importação síncrona sem fila** — rejeitado; inconsistente com demais importações admin.

## Consequências

- Modelo de planilha em `planilhas/fruta_loja_vinculo.xlsx`.
- Tabela `cliente_fruta_importacoes` para estado do job de preview.
- [PLAN-0085](../plans/PLAN-0085-importacao-vinculo-fruta-loja-planilha.md).
