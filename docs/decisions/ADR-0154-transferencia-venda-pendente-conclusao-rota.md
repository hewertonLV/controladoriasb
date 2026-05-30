# ADR-0154: Transferência e venda pendente na conclusão da rota

**Data:** 2026-05-28
**Status:** Substituído por [ADR-0157](ADR-0157-demandas-rota-sem-movimentacao-imediata.md), [ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md) e [ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md)
**Contexto:** Captação — conclusão de rota na matriz

> **Nota (2026-05-28):** A movimentação imediata na conclusão foi revogada. Mantido como histórico; implementação vigente segue o pacote [PACOTE-0157](../pacotes/PACOTE-0157-demandas-rota-sem-movimentacao.md).

## Contexto

Lojas podem ter saída física de estoque (`id_unidade_negocio_saida_venda` efetiva) em unidade diferente da unidade de faturamento do lote. Ao concluir a rota, a operação precisa registrar a transferência de frutas agregada por rota e uma demanda de venda vinculada, concluível somente após a transferência ser recebida.

## Decisão

- **Gatilho:** `CaptacaoMatrizRotasService::concluirRota`, após validações existentes.
- **Condição por pedido:** `SaidaEstoqueFisicoCaptacaoService::idSaidaEfetiva()` ≠ `captacao_lotes.id_unidade_negocio_galpao` (saída física fora do galpão operacional do lote, ex.: HUB).
- **Transferência:** uma por combinação `(lote, rota, unidade origem, fruta)` com total das quantidades dos pedidos elegíveis da rota; origem = saída física efetiva; destino = galpão operacional do lote; numeração `CAP-TR-{loteId}-R{rotaId}-{frutaId}` em `numero_nf_origem`; vínculo em `captacao_lote_movimentacoes` com `id_captacao_rota`.
- **Exceção a ADR-0065:** transferências geradas pela captação na conclusão da rota permanecem `PENDENTE_RECEBIMENTO` (saída na origem, entrada no destino só após confirmação manual no módulo Transferências).
- **Venda (com transferência):** uma `VendaNota` por loja elegível na rota, `status_conclusao = AGUARDANDO_TRANSFERENCIA`, sem movimentações de saída até todas as transferências da loja ficarem `RECEBIDA_CONFORME`; estoque debitado do galpão operacional ao concluir a venda.
- **Venda (sem transferência):** pedidos da rota cuja saída física é o galpão operacional do lote **não** geram transferência; na conclusão da rota cria-se somente a `VendaNota` com movimentações imediatas (`status_conclusao = CONCLUIDA`).
- **Upload NF / GerarVendasCaptacaoLoteService:** pedidos com rota vinculada (`id_captacao_rota`) não geram venda no upload de NF — ficam para a conclusão da rota (com ou sem transferência).
- **Idempotência:** reconclusão da mesma rota não duplica registros já vinculados ao par `(lote, rota, fruta, origem)` ou `(lote, rota, pedido)`.

## Alternativas consideradas

- **Manter transferência no pipeline de saída físico (lote inteiro)** — não reflete fechamento operacional por rota.
- **Venda imediata debitando HUB (ADR-0135)** — rejeitada para estes pedidos; exige estoque na unidade de faturamento após transferência.

## Consequências

- [PLAN-0154](../plans/PLAN-0154-transferencia-venda-pendente-conclusao-rota.md).
- Reintroduz ação **Confirmar recebimento** no módulo Transferências, restrita a transferências `PENDENTE_RECEBIMENTO` de captação.
- Complementa [ADR-0152](ADR-0152-rota-concluida-matriz-por-rota.md).
