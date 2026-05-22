# PLAN-0050: Venda com origem em unidade de produção — custo operacional do HUB

**ADR:** [ADR-0050](../decisions/ADR-0050-venda-unidade-producao-custo-hub.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir marcar unidades de produção e, na venda com origem produção, incluir opcionalmente o CO de um HUB na margem.

## Pré-requisitos

- Tabela `unidades_negocio` e histórico `historico_c_o_un_ng` vigentes.
- Fluxo de venda e rateio de frete existentes.

## Passos

1. **Migration** — coluna `is_unidade_producao` em `unidades_negocio`.
2. **Cadastro UN** — switch no formulário + validação + auditoria.
3. **Venda** — UI (switch + select HUB), request e `VendaMovimentacaoService`.
4. **Frete** — `FreteRateioMovimentacaoService::atualizarVenda` desconta CO no resultado.
5. **Testes** — feature venda com produção (switch sim/não).
6. **Docs** — ADRs antigas: referência “Olho de Fabio” (renomeação em ADR-0039).

## Critério de conclusão

- UN com `is_unidade_producao` exibe bloco na venda; CO do HUB entra na margem quando switch SIM; testes passam.

## Riscos

- Esquecer rateio de frete após CO — mitigação: teste com frete + produção + CO.
