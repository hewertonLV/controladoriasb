# PLAN-0162: Demanda de transferência agregada por rota

**ADR:** [ADR-0162](../decisions/ADR-0162-demanda-transferencia-rota-agregada.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Uma demanda de transferência por rota (quando origem ≠ galpão de faturamento), com várias frutas agregadas e uma única transferência SB ao concluir.

## Pré-requisitos

- ADR-0157/0158 implementados
- Tabela `captacao_lote_movimentacoes` com `id_captacao_rota` e `id_unidade_negocio_origem`

## Passos

1. **Migration** — `captacao_lote_movimentacao_linhas`; consolidar registros legados; ajustar índice.
2. **Modelo** — `CaptacaoLoteMovimentacaoLinha` + relação `linhas` no cabeçalho.
3. **Domínio** — `EfetivarDemandasMovimentacaoRotaCaptacaoService` cria/atualiza cabeçalho + linhas.
4. **Ciclo transferência** — validar estoque, Cigam, NF e movimentação multi-fruta com mesmo `transferencia_origem_id`.
5. **UI/exibição** — card e detalhe listam todas as linhas.
6. **Testes** — rota com 2 frutas gera 1 demanda com 2 linhas e 1 grupo de transferência.

## Critério de conclusão

- Concluir rota com 3 frutas no HUB gera 1 demanda, 3 linhas, 1 card no módulo Transferências.
- Anexar NF cria movimentações com o mesmo `transferencia_origem_id`.

## Riscos

- Dados legados com N demandas por rota — mitigar migration de consolidação.
