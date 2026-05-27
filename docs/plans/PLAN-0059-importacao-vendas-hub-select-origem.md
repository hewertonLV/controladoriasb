# PLAN-0059: Importação de vendas — HUB no select de origem

**ADR:** [ADR-0059](../decisions/ADR-0059-importacao-vendas-hub-select-origem.md)
**Data:** 2026-05-22
**Status:** Concluído

## Objetivo

Incluir unidades HUB no select de origem da importação de NF de vendas, com faturamento na confirmação.

## Pré-requisitos

- ADR-0053 (override origem) implementado
- Venda manual com regra HUB + faturamento

## Passos

1. **API** — incluir HUB em `empresas_origem`; expor `unidades_faturamento`.
2. **Confirmação** — aceitar faturamento por row; validar e repassar ao service.
3. **UI** — label HUB; select faturamento condicional.
4. **Testes** — resultado inclui HUB nas opções.

## Critério de conclusão

Select de origem lista HUB; ao selecionar HUB aparece faturamento; confirmação grava venda com faturamento.

## Riscos

- Origem HUB sem faturamento — erro explícito na confirmação.
