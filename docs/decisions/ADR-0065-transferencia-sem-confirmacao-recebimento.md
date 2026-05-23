# ADR-0065: Transferência sem confirmação de recebimento

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Remoção do fluxo de conferência/recebimento no destino para transferências internas.

## Contexto

O sistema mantinha transferências com saída imediata na origem e entrada pendente no destino até conferência (conforme/divergente), com reenvio após divergência. A operação passou a tratar quem registra a transferência como responsável pelos dados informados.

## Decisão

Ao criar uma transferência interna, o par saída/entrada é efetivado imediatamente: baixa na origem, crédito no destino e status operacional `RECEBIDA_CONFORME`. Não há etapa de recebimento, divergência nem reenvio. Cancelamento (normal ou admin) estorna origem e destino quando a transferência estava conforme.

## Alternativas consideradas

- Manter conferência opcional — rejeitada por complexidade operacional e duplicidade de responsabilidade
- Conferência só com frete — rejeitada por regra assimétrica difícil de explicar ao usuário

## Consequências

- Rotas, actions, requests e UI de recebimento/reenvio removidos
- Permissões `receber` e `reenviar` permanecem no enum (legado) sem uso em rotas
- Status `PENDENTE_RECEBIMENTO`, `RECEBIDA_DIVERGENTE` e `REENVIADA` permanecem no enum para dados históricos
- Realocação HUB e rateio de frete continuam filtrando transferências `RECEBIDA_CONFORME`
