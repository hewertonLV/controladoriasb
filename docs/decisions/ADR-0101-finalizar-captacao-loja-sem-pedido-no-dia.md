# ADR-0101: Finalizar captação ignora lojas sem pedido no dia

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Finalização da captação ([ADR-0087](ADR-0087-captacao-por-loja-conclusao-margem-alvo.md), [ADR-0098](ADR-0098-captacao-portao-quantidade-vinculo-rota-pos-vendas.md))

## Contexto

A finalização exigia `captacao_concluida = true` em **todas** as lojas com frutas vinculadas na carteira. Lojas que não pedem em determinado dia (sem quantidade digitada) ficavam pendentes e bloqueavam o lote, embora não houvesse captação a encerrar.

## Decisão

- **Finalizar captação (faturamento):** exige conclusão apenas das lojas que **informaram quantidade > 0** no lote da data.
- Loja com frutas vinculadas mas **sem pedido** ou **sem nenhuma quantidade > 0** no lote **não bloqueia** a finalização.
- Loja com quantidade > 0 e `captacao_concluida = false` continua bloqueando até clicar **Concluir** na matriz ou captação por loja.

## Alternativas consideradas

- Exigir conclusão explícita mesmo com qty zero — rejeitada; operação não captura loja que não pediu.
- Remover loja da carteira quando não pede — rejeitada; cadastro permanece; só não participa daquele dia.

## Consequências

- Mensagem de pendentes na finalização lista só lojas com qty digitada e não concluídas.
- Atualiza [ADR-0087](ADR-0087-captacao-por-loja-conclusao-margem-alvo.md) quanto ao gate de finalização.
- [PLAN-0101](../plans/PLAN-0101-finalizar-captacao-loja-sem-pedido-no-dia.md).
