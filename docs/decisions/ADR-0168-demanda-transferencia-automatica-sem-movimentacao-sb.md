# ADR-0168: Demanda automática de transferência — sem movimentação no SB

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Demanda de transferência gerada na conclusão da rota ([ADR-0157](ADR-0157-demandas-rota-sem-movimentacao-imediata.md), [ADR-0162](ADR-0162-demanda-transferencia-rota-agregada.md))

## Contexto

Demandas automáticas (`id_captacao_rota` preenchido) existem para faturamento fiscal no CIGAM: arquivo Cigam, NF e registro de controle no SB Controladoria. O estoque físico/fiscal da rota **não** deve ser movimentado por transferência no SB nesse fluxo — diferente das demandas **manuais** no módulo Transferências ([ADR-0160](ADR-0160-demanda-transferencia-manual-multi-fruta.md)), que ao concluir criam movimentações SB.

## Decisão

- **Automática** (`captacao_lote_movimentacoes.id_captacao_rota` ≠ null): ciclo `ABERTO` → `INICIADO` → `CONCLUIDO` com Cigam/NF; **nunca** chamar `TransferenciaMovimentacaoService` nem preencher `transferencia_origem_id`.
- **Manual** (`TransferenciaDemanda` no módulo Transferências): mantém criação de movimentações SB ao concluir (após frete, [ADR-0161](ADR-0161-vincular-frete-demanda-manual.md)).
- **Correção de legado:** comando `captacao:reverter-movimentacao-transferencia-automatica {demanda}` cancela transferência SB pendente e zera `transferencia_origem_id` em demandas automáticas que foram geradas indevidamente.
- Atualiza interpretação de [ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md): “efetivar movimentação em `CONCLUIDO`” aplica-se apenas a demandas que **não** são automáticas da rota; automáticas concluem somente o registro fiscal/CIGAM.

## Alternativas consideradas

- **Manter movimentação SB na automática (ADR-0158 literal)** — rejeitada; duplica operação fiscal e contradiz orientação operacional (somente CIGAM).
- **Unificar automática e manual no mesmo serviço** — rejeitada; ciclos e entidades já são distintos ([ADR-0160](ADR-0160-demanda-transferencia-manual-multi-fruta.md)).

## Consequências

- [PLAN-0168](../plans/PLAN-0168-demanda-transferencia-automatica-sem-movimentacao-sb.md).
- `CaptacaoDemandaTransferenciaRotaService::anexarNfEConcluir` não cria par de movimentações quando `id_captacao_rota` está preenchido.
- Venda da rota permanece desacoplada ([ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md)); não depende de recebimento de transferência SB inexistente.
