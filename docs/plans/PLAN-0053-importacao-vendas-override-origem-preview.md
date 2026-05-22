# PLAN-0053: Importação de vendas — alterar origem na prévia

**ADR:** [ADR-0053](../decisions/ADR-0053-importacao-vendas-override-origem-preview.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir trocar a unidade de origem por NF+cliente no preview antes de confirmar a importação de vendas.

## Pré-requisitos

- Fluxo ADR-0048 operacional
- Permissão `movimentacoes.vendas.importar-confirmar`

## Passos

1. **API resultado** — retornar `empresas_origem`.
2. **API confirmar** — aplicar `id_empresa_origem_por_row` com validação (UN com estoque, não HUB).
3. **UI** — select de origem agrupado por NF + cliente; cliente fixo na célula agrupada.
4. **Testes** — override e lista de origens.

## Critério de conclusão

Preview com select agrupado; confirmação grava com origem alterada; testes passando.

## Riscos

- Origem sem estoque da fruta — erro ao gravar via `registrarVenda`.
