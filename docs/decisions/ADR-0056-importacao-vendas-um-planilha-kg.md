# ADR-0056: Importação de vendas — UM da planilha em KG ou cadastrada

**Data:** 2026-05-22
**Status:** Aceito
**Contexto:** Importação de vendas NF (ADR-0048)

## Contexto

Lojas exportam NF com quantidade na UM cadastrada da fruta (ex.: CAIXA). Outras exportam quantidade já em KG. A regra anterior exigia que a coluna F fosse idêntica à UM da fruta, gerando erro em planilhas legítimas em KG.

## Decisão

Na importação de vendas, a coluna F aceita:

1. **A UM cadastrada da fruta** — quantidade da coluna E é usada diretamente como `qtd_fruta_um`.
2. **`KG`** — quantidade da coluna E está em kg; converter para `qtd_fruta_um` dividindo por `fruta.kg_por_unidade_medicao`, sem aplicar conversão adicional na confirmação.

Qualquer outra UM continua inválida. A prévia guarda `qtd_planilha` e `unidade_medicao_planilha` para exibição; a confirmação usa `qtd_fruta_um` já normalizado.

## Alternativas consideradas

- **Sempre exigir UM da fruta** — rejeitado; não atende lojas que faturam em kg.
- **Aceitar qualquer UM da enum** — rejeitado; ambiguidade sem fator de conversão confiável.

## Consequências

- Helper `VendaImportacaoQuantidade` centraliza a conversão.
- Prévia exibe `20 KG → 2 CAIXA` quando houver conversão.
- ADR-0048 permanece válida para demais colunas e duplicidade (usa valores brutos da planilha).
