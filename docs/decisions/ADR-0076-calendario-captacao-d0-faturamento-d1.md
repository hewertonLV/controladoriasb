# ADR-0076: Captação no dia D; faturamento no dia seguinte (ou na saída)

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Resposta ao item C (timing captação vs venda/NF); [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)

## Contexto

A operação **monta a captação no dia D** (pedidos, romaneios, transferências Lucas) e **fatura no Cigan no dia seguinte (D+1)** em regra. Alterações de preço/quantidade após a captação devem ser refletidas no faturamento **o mais próximo possível da saída física** da mercadoria, para evitar cancelar NF e reemitir.

## Decisão

- **Dia D:** captação de pedidos, finalização da captação ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md)), pipeline Lucas (Cigan transferência + transferência gerencial + frete).
- **D+1 (padrão):** Jefferson — Iniciar faturamento / Cigan vendas / **Finalizar venda** no SB (movimentações efetivadas).
- **Exceção:** quando a saída física for no **mesmo dia D** ou houver mudança tardia, faturar na **data/hora mais próxima da saída**, usando preços **vigentes no momento do Finalizar venda** (respeitando travas de [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)).
- Pedidos podem registrar `data_entrega` / `data_saida_prevista` para ordenar fila de faturamento Jefferson.
- Movimentações de venda continuam **somente** em **Finalizar venda** (não na captação) — alinhado a Cigan oficial antes da baixa gerencial.

## Alternativas consideradas

- **Baixa de estoque na captação do dia** — rejeitado; conflita com D+1 e com transferência/abastecimento prévio.
- **Sempre D+1 rígido sem exceção** — rejeitado; operação pediu proximidade à saída física.

## Consequências

- Telas Jefferson ordenadas por data de saída; alertas de pedidos D captados e pendentes faturamento.
- [PLAN-0067](PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md) e UI de fila de faturamento.
