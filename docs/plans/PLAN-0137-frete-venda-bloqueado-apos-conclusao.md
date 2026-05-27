# PLAN-0137: Bloqueio frete venda após conclusão

**ADR:** [ADR-0137](../decisions/ADR-0137-frete-venda-bloqueado-apos-conclusao.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Impedir alteração de frete de vendas após `VENDAS_FINALIZADAS`, exceto administrador.

## Passos

1. `permiteEdicaoFreteVenda` + `assertPodeAlterarFreteVenda` no serviço.
2. Controller e blade somente leitura.
3. Testes operador bloqueado e admin liberado.

## Critério de conclusão

POST frete venda-loja retorna erro após conclusão para não-admin; admin altera com sucesso.
