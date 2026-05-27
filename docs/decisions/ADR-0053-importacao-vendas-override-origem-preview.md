# ADR-0053: Importação de vendas — alterar origem na prévia

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Importação de vendas por planilha (ADR-0048), espelhando ADR-0052 (transferências)

## Contexto

Após a análise, a origem vem do CNPJ da coluna B e o cliente (destino da venda) da coluna C. Na operação, a unidade de origem real às vezes difere da planilha; o cliente permanece o informado.

## Decisão

Na prévia (linhas prontas), exibir **select** de unidades de negócio com estoque (incluindo **HUB**), pré-selecionado com a origem da planilha.

Quando a origem efetiva for HUB, exibir também select de **unidade de faturamento** (não-HUB) no mesmo grupo NF + cliente.

O select é **agrupado por NF + cliente** (`id_empresa_destino`): alterar a origem de uma NF aplica a todas as linhas prontas com o mesmo número de NF e mesmo cliente.

Na confirmação, aceitar mapa opcional `id_empresa_origem_por_row`. O agrupamento em nota continua por NF + origem (efetiva) + cliente.

## Alternativas consideradas

- **Alterar cliente na prévia** — rejeitado: pedido explícito de manter destino.
- **Select por linha** — rejeitado: operação ajusta por remessa/NF, não por fruta.

## Consequências

- Endpoint `resultado` inclui `empresas_origem`.
- Override de origem na confirmação; validação rejeita origem sem estoque da fruta ao gravar; origem HUB exige faturamento.
- Duplicidade na planilha (ADR-0048) continua baseada nos campos da planilha, não no override.
