# ADR-0171: Importação de lojas na carteira por ID CIGAM

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Edição de carteira de captação ([ADR-0086](ADR-0086-multiplas-carteiras-mesmo-faturamento-galpao.md))

## Contexto

Carteiras agrupam lojas (`clientes.id_captacao_carteira`) do mesmo faturamento. Vincular dezenas de clientes manualmente na tela de edição não escala; a operação trabalha com códigos CIGAM.

## Decisão

- Planilha Excel: **coluna A** = `id_cigam` do cliente; linha 1 = cabeçalho; **um cliente por linha**.
- Importação na tela **Editar carteira**, escopo fixo da carteira aberta.
- **Aditiva:** vincula lojas selecionadas no preview **sem** desmarcar as já vinculadas à carteira.
- Resolução: cliente deve existir na **unidade de faturamento** da carteira e estar **sem carteira** ou **já nesta carteira** (já vinculado → «sem alteração»).
- Cliente de **outra carteira** → erro na linha.
- Fluxo assíncrono padrão: upload → preview → confirmar.
- Permissão: `captacao.lote.visualizar` (mesma da edição de carteira).
- Modelo: `planilhas/carteira_lojas_vinculo.xlsx`.

## Alternativas consideradas

- **Substituir todos os vínculos da carteira pela planilha** — rejeitada; risco de apagar lojas não listadas.
- **Importação síncrona** — rejeitada; inconsistente com demais importações admin.

## Consequências

- [PLAN-0171](../plans/PLAN-0171-importacao-lojas-carteira-id-cigam.md).
- Tabela `captacao_carteira_importacoes` com FK `id_captacao_carteira`.
