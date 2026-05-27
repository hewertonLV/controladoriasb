# ADR-0132: Upload NF de venda finaliza vendas no SB

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Faturamento Cigam ([ADR-0126](ADR-0126-arquivo-cigan-edi-vendas-faturamento.md), [ADR-0118](ADR-0118-upload-nf-transferencia-cigan.md))

## Contexto

Na transferência, o operador envia a NF após importar o TXT no Cigam e o SB avança de etapa. No faturamento, existia apenas o botão «Finalizar vendas SB» sem armazenar a NF importada no Cigam.

## Decisão

- Na aba **Arquivo Cigam Venda**, com status `FATURAMENTO_CIGAN_INICIADO`, exibir upload de NF (XML, PDF ou TXT), no mesmo padrão da NF de transferência.
- Ao enviar com sucesso: gravar `arquivo_nf_venda_*`, executar `GerarVendasCaptacaoLoteService` (movimentações de venda) e transicionar para `VINCULAR_ROTAS_NOS_PEDIDOS` ([ADR-0133](ADR-0133-status-vincular-rotas-pos-nf-venda.md)); `VENDAS_FINALIZADAS` só após rotas completas.
- Remover a ação de pipeline «Finalizar vendas SB» no faturamento; conclusão do ciclo via etapa de rotas ([ADR-0133](ADR-0133-status-vincular-rotas-pos-nf-venda.md)).
- Permissão do upload: `captacao.lote.venda.finalizar` (mesmo ator que finalizava vendas).

## Alternativas consideradas

- Manter botão e upload — rejeitado; duplicaria a finalização.
- Só armazenar NF sem movimentar — rejeitado; operação pediu efetivar vendas no envio.

## Consequências

- [PLAN-0132](../plans/PLAN-0132-upload-nf-venda-finaliza-vendas.md).
- Download da NF enviada disponível em `VENDAS_FINALIZADAS`.
