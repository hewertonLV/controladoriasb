# PLAN-0106: Código do cliente na unidade de negócio

**ADR:** [ADR-0106](../decisions/ADR-0106-unidade-negocio-codigo-cliente.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir cadastrar e importar o código CIGAM do cliente principal vinculado a cada unidade de negócio.

## Pré-requisitos

- Tabela `clientes` com `id_cigam` e `id_unidade_negocio`.

## Passos

1. **Migration** — `id_cliente` nullable, FK `clientes`, unique.
2. **Model** — relação `clientePrincipal()`, fillable, accessor `codigo_cliente`.
3. **Validação** — trait de requests + regra na edição.
4. **UI** — formulário (select), listagem (coluna), histórico, importação (coluna L).
5. **Testes** — `UnidadeNegocioTest`, `UnidadeNegocioImportacaoTest`.

## Critério de conclusão

- Edição persiste `id_cliente` com validação de pertencimento à UN.
- Listagem e histórico exibem o código CIGAM do cliente.
- Importação atualiza `id_cliente` pela coluna L quando válido.
- Testes do módulo passam.

## Riscos

- UN sem clientes cadastrados — campo vazio até vínculo; mitigação: opcional e editável depois.
