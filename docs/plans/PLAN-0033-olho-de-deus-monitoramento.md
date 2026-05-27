# PLAN-0033: Olho de Fabio (mesmo da ADR)

**ADR:** [ADR-0033](../decisions/ADR-0033-olho-de-deus-monitoramento.md)
**Data:** 2026-05-20
**Status:** Concluído

## Objetivo

Entregar monitoramento de prejuízos com atualização automática na página, sem impactar outros usuários.

## Passos

1. Config `config/olho_de_deus.php` e permissão.
2. `OlhoDeDeusAlertaService` + `OlhoDeDeusController`.
3. View + `olho-de-deus.js` com polling condicionado à aba visível.
4. Menu lateral e testes.

## Critério de conclusão

- Usuário autorizado vê alertas ao registrar venda com preço abaixo do custo.
- Poll retorna 429 após exceder throttle.
