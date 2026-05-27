# ADR-0055: Importação de estoques — switch de custo operacional na prévia

**Data:** 2026-05-22
**Status:** Aceito
**Contexto:** Importação de estoques (ADR-0036)

## Contexto

A planilha informa quantidade (UM) e preço total da posição. O custo operacional (CO) vigente da unidade é R$/kg e entra no preço médio em compras; na carga inicial de estoque a operação precisa optar linha a linha se o CO da unidade compõe o preço médio importado.

## Decisão

Na prévia (novas e alterações), cada linha exibe switch **“Incluir custo operacional”**, **ligado por padrão**, sempre editável.

Na confirmação, aceitar mapa opcional `aplicar_custo_operacional_por_row` (`row_id` → bool). Quando `true`, somar o CO da unidade ao `preco_medio_kg` derivado da planilha antes de `definirPosicaoAbsoluta` — mesma unidade (R$/kg) usada na compra.

O CO exibido vem do histórico vigente (`status_position = true`); se ausente, usa o campo `custo_operacional` do cadastro da unidade.

A API de resultado da prévia re-enriquece `custo_operacional_kg` na leitura, para prévias geradas antes do deploy ou por worker com código antigo em cache.

## Alternativas consideradas

- **Coluna extra na planilha** — rejeitado; decisão é por linha na UI, não no Excel.
- **Sempre incluir CO** — rejeitado; operação precisa poder importar preço “puro” da planilha.
- **Agrupar por unidade** — rejeitado; pedido é switch em **todos** os itens.

## Consequências

- Preview inclui `custo_operacional_kg` por linha (histórico vigente ou cadastro da unidade).
- Valor total acumulado reflete `qtd_kg × (preco_medio_kg + CO)` quando o switch está ligado na confirmação.
