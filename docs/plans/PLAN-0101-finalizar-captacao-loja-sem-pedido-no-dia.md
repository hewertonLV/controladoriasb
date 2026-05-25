# PLAN-0101: Finalizar captação ignora lojas sem pedido no dia

**ADR:** [ADR-0101](../decisions/ADR-0101-finalizar-captacao-loja-sem-pedido-no-dia.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Permitir finalizar captação quando lojas da carteira não pediram no dia (sem quantidade no lote).

## Pré-requisitos

- ADR-0087 e ADR-0098 implementados.

## Passos

1. **Domínio** — `lojasComPedidoNaoConcluido()` e ajuste em `todasLojasElegiveisConcluidas()`.
2. **Action** — mensagem de pendentes usa só lojas com qty > 0 não concluídas.
3. **Testes** — bloqueio com qty pendente; liberação com loja sem pedido.

## Critério de conclusão

- Loja sem qty no lote não impede finalizar.
- Loja com qty e não concluída continua bloqueando.
- Testes de finalização passam.

## Riscos

- Loja com qty parcial esquecida sem concluir — mitigado: continua bloqueando se houver qty > 0.
