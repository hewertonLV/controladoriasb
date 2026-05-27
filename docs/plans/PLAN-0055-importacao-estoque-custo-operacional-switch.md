# PLAN-0055: Importação de estoques — switch de custo operacional na prévia

**ADR:** [ADR-0055](../decisions/ADR-0055-importacao-estoque-custo-operacional-switch.md)
**Data:** 2026-05-22
**Status:** Concluído

## Objetivo

Permitir incluir ou não o CO vigente da unidade no preço médio/kg de cada linha na confirmação da importação de estoques.

## Pré-requisitos

- Importação ADR-0036 operacional
- Permissão `estoques.importar-confirmar`

## Passos

1. **Preview** — anexar `custo_operacional_kg` por linha no processor.
2. **Domínio** — helper para somar CO ao preço médio/kg.
3. **Confirmar** — aplicar `aplicar_custo_operacional_por_row`.
4. **UI** — switch por linha (novas e alterações), padrão ligado.
5. **Testes** — unit helper + feature confirmar com/sem CO.

## Critério de conclusão

Switch visível na prévia; confirmação grava preço com CO somado quando ligado; testes passando.

## Riscos

- Unidade sem CO com switch ligado — erro explícito na linha.
