# PLAN-0104: Preço editável até Faturamento Cigan iniciado

**ADR:** [ADR-0104](../decisions/ADR-0104-preco-editavel-ate-faturamento-cigan.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Permitir edição de preço na matriz até `FATURAMENTO_CIGAN_INICIADO`; bloquear depois.

## Pré-requisitos

- Matriz e `PedidoService::upsertCelulaMatriz` existentes.

## Passos

1. **Enum** — ajustar `permiteEdicaoPreco()`.
2. **PedidoService** — fluxo somente-preço pós-captação.
3. **Matriz UI** — qty vs preço independentes; link «Editar preços» no lote.
4. **Testes** — preço OK em transferência; bloqueado em faturamento Cigan.

## Critério de conclusão

- Preço alterável em `AGUARDANDO_TRANSFERENCIA_CIGAN` … `TRANSFERENCIA_FINALIZADA`.
- Preço bloqueado em `FATURAMENTO_CIGAN_INICIADO`.
- Quantidade continua bloqueada após captação.

## Riscos

- Edição de preço com loja concluída — mitigado: só preço, qty congelada.
