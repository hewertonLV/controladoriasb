# ADR-0158: Ciclo da demanda de transferência na captação

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Demandas automáticas de transferência geradas na conclusão da rota ([ADR-0157](ADR-0157-demandas-rota-sem-movimentacao-imediata.md))

## Contexto

Demandas de transferência da captação precisam seguir fluxo operacional semelhante à antiga fase **Transferência Cigam iniciada**: arquivo Cigam, NF fiscal, só então movimentação no SB.

## Decisão

- **Status** (`captacao_lote_movimentacoes.status_demanda`): `ABERTO` → `INICIADO` → `CONCLUIDO` (enum existente; novos gatilhos).
- **`ABERTO`:** demanda criada na conclusão da rota; editável/removível apenas via reabertura da rota ([ADR-0155](ADR-0155-status-demanda-rota-reabrir.md)).
- **`INICIADO`:** usuário aciona **Iniciar transferência** na UI da demanda. Pré-requisito: estoque na unidade de origem ≥ quantidade total da demanda, **por fruta**. A tela deve listar explicitamente: frutas com saldo suficiente, frutas sem saldo e quantidades faltantes. Após iniciar, a demanda **não** pode ser alterada (origem, destino, quantidades); permitido **excluir** ou **concluir**. Confirmação obrigatória ao iniciar, informando essa trava.
- **Comportamento em `INICIADO`:** igual transferência Cigam iniciada — gerar/baixar arquivo Cigam, aguardar anexo da NF de transferência; upload da NF avança automaticamente para `CONCLUIDO`.
- **`CONCLUIDO`:** ao anexar NF, a demanda conclui o registro fiscal/CIGAM. **Demanda automática da rota** (`id_captacao_rota` preenchido): **sem** movimentação SB — ver [ADR-0168](ADR-0168-demanda-transferencia-automatica-sem-movimentacao-sb.md). Demandas manuais no módulo Transferências: movimentação SB ao concluir ([ADR-0160](ADR-0160-demanda-transferencia-manual-multi-fruta.md)). Até `CONCLUIDO`, **nenhuma** movimentação de estoque na automática.

## Alternativas consideradas

- **Confirmar recebimento como gatilho de `INICIADO` (ADR-0155 anterior)** — rejeitado; iniciar precede o fluxo Cigam/NF.
- **Movimentar ao iniciar** — rejeitado; movimentação só em `CONCLUIDO`.

## Consequências

- [PLAN-0158](../plans/PLAN-0158-ciclo-demanda-transferencia-captacao.md).
- Reutilizar geradores/upload de NF de transferência Cigam existentes, adaptados ao escopo **por demanda/rota** em vez de lote inteiro quando necessário.
- Cards de demanda na aba Por rota refletem status e ações (iniciar, excluir, anexar NF).
