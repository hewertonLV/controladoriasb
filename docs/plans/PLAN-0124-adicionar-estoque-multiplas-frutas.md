# PLAN-0124: Adicionar estoque — múltiplas frutas e unidade fixa

**ADR:** [ADR-0124](../decisions/ADR-0124-adicionar-estoque-multiplas-frutas.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Formulário Adicionar Estoque com unidade fixa e várias frutas por envio.

## Pré-requisitos

- Permissão `estoques.movimentar`
- Acesso à unidade informada na URL

## Passos

1. Atualizar `StoreMovimentacaoEstoqueRequest` para `itens[]`.
2. Ajustar `EstoqueController` (form + store em lote).
3. Reescrever `movimentar.blade.php` com linhas dinâmicas.
4. Renomear botões em `unidade.blade.php` e `show.blade.php`.
5. Testes de feature para multi-itens e unidade obrigatória.

## Critério de conclusão

- Entrada de N frutas em uma transação; redirect para `admin.estoques.unidade`.
- Testes PHPUnit verdes.

## Riscos

- Falha em um item reverte todo o lote — mitigação: transação única e mensagem clara.
