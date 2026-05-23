# PLAN-0065: Transferência sem confirmação de recebimento

**ADR:** [ADR-0065](../decisions/ADR-0065-transferencia-sem-confirmacao-recebimento.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Transferências internas efetivam saída e entrada na criação, sem fluxo de conferência no destino.

## Pré-requisitos

- ADR-0065 aceita
- `TransferenciaMovimentacaoService` com efetivação imediata no destino

## Passos

1. **Serviço** — `efetivarEntradaConforme()` ao final de `criarTransferenciaInterno()`; cancelamento e frete só para `RECEBIDA_CONFORME`
2. **Remover HTTP** — controller/action/request de recebimento e reenvio; rotas em `web.php`
3. **UI** — simplificar `transferencias/show.blade.php` (sem formulário de conferência)
4. **Testes** — ajustar expectativas e remover cenários de divergência/reenvio
5. **Documentação** — ADR-0065 e este plano

## Critério de conclusão

- Criação de transferência credita destino e marca `RECEBIDA_CONFORME`
- Rotas `recebimento.store` e `reenviar` inexistentes
- Suite `--filter=Transferencia` e fluxos integrados que usam transferência passando

## Riscos

- Dados legados com status pendente/divergente — mitigação: enums mantidos; cancelamento admin continua disponível
