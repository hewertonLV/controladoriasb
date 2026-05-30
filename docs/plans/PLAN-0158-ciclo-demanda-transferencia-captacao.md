# PLAN-0158: Ciclo da demanda de transferência na captação

**ADR:** [ADR-0158](../decisions/ADR-0158-ciclo-demanda-transferencia-captacao.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Implementar fluxo ABERTO → INICIADO → CONCLUIDO para demandas automáticas de transferência da rota, com estoque na origem ao iniciar e Cigam/NF antes de movimentar.

## Pré-requisitos

- [PLAN-0157](PLAN-0157-demandas-rota-sem-movimentacao-imediata.md) concluído.

## Passos

1. **API iniciar** — endpoint PATCH/POST demanda transferência: validar saldo por fruta na origem; retornar detalhe `{fruta, qtd_demanda, qtd_disponivel, falta}`; transição para `INICIADO`; confirmação no front.
2. **UI demanda** — card/lista na aba Por rota: botões Iniciar, Excluir; em `INICIADO`: download Cigam, upload NF (reutilizar requests/geradores existentes adaptados por demanda).
3. **Concluir** — ao anexar NF válida: status `CONCLUIDO` + chamar movimentação de transferência (pendente recebimento conforme ADR-0065 exceção captação).
4. **Trava edição** — após `INICIADO`, bloquear alteração de origem/destino/qtd no backend.
5. **Testes** — iniciar sem estoque → 422 com lista de faltas; iniciar com estoque → INICIADO; NF → CONCLUIDO + movimentação.

## Critério de conclusão

- Demanda HUB→galpão percorre fluxo completo sem movimentação antes de `CONCLUIDO`.

## Riscos

- Escopo Cigam por demanda vs por lote — alinhar layout EDI com uma rota/fruta agregada.
