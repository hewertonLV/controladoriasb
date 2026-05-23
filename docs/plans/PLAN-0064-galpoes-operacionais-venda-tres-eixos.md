# PLAN-0064: Galpões operacionais e três eixos na venda

**ADR:** [ADR-0064](../decisions/ADR-0064-galpoes-operacionais-venda-tres-eixos.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Suportar galpões sem CNPJ, centro de resultado na venda, CO do galpão e margem por galpão nos relatórios.

## Pré-requisitos

- ADR-0064 aceita
- ADR-0060/0061/0063 implementados

## Passos

1. **Migration** — `is_galpao_operacional`; `id_unidade_negocio_centro_resultado` em `movimentacoes` e `vendas_notas`.
2. **Cadastro UN** — flag galpão no model, form, validação (mutuamente exclusivo com HUB).
3. **VendaMovimentacaoService** — resolver PM/centro/CO; realocação só loja+HUB.
4. **Request + UI** — select centro resultado; excluir galpão de faturamento.
5. **RentabilidadeLojaService** — agrupar por centro de resultado.
6. **Testes** — galpão Barbalha; HUB direto mantém CO loja.

## Critério de conclusão

- Venda NF Barbalha + centro galpão debita PM galpão, CO galpão, margem no relatório por galpão.
- Venda HUB direta loja mantém CO loja e realocação ADR-0061.
- Testes passam.

## Riscos

- Venda HUB + centro galpão sem estoque no galpão — validar saldo no galpão.
