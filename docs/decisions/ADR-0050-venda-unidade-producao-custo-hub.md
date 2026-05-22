# ADR-0050: Venda com origem em unidade de produção — custo operacional do HUB

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Vendas originadas em fazendas (ex.: FAZENDA MV) e rentabilidade no dashboard Olho de Fabio

## Contexto

Algumas unidades de negócio são também **unidades de produção** (fazendas). Na venda, o custo da saída usa o preço médio do estoque; a operação pode precisar incluir o custo operacional (CO) de um **HUB** na margem, sem alterar a baixa contábil do estoque pelo preço médio.

## Decisão

- Flag `is_unidade_producao` em `unidades_negocio` (independente de `is_hub` e `possui_estoque`).
- Na tela de venda, quando a **origem física** for unidade de produção:
  - Switch **“Incluir custo operacional do HUB?”** — padrão **SIM**.
  - Com SIM: select obrigatório de unidade com `is_hub = true`; CO vigente (R$/kg) do HUB é snapshot em `id_custo_operacional` / `valor_custo_operacional`.
  - Com NÃO: campos ocultos; CO zerado na movimentação.
- **Margem:** `resultado_movimentacao = valor_nf_total − valor_custo_saida − (valor_custo_operacional × qtd_fruta_kg) − valor_frete_rateio`.
- **Estoque:** `valor_custo_saida` continua `preco_medio_kg × qtd_kg` (CO não infla a baixa do estoque).
- Unidade de **faturamento** (regra HUB existente) permanece separada do HUB de custo.

## Alternativas consideradas

- Somar CO em `valor_custo_saida` — rejeitado; distorceria saldo e valor acumulado do estoque.
- Usar unidade de faturamento como fonte do CO — rejeitado; conceitos distintos (ICMS/faturamento vs custo HUB).

## Consequências

- Rentabilidade e alertas do **Olho de Fabio** refletem a margem com CO quando o switch estiver ativo.
- Cadastro/importação de unidades deve permitir marcar produção; FAZENDA MV configurada manualmente ou via seed local.
- Importação de vendas por planilha fica fora desta entrega (mesma regra pode ser estendida depois).
