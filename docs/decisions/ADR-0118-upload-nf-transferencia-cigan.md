# ADR-0118: Upload da NF de transferência na aba Arquivo Cigan

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Pipeline Lucas ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md), [ADR-0103](ADR-0103-matriz-aba-arquivo-cigan-txt.md))

## Contexto

Após baixar o TXT e importar no Cigan, Lucas obtém a NF de transferência. Antes de avançar para vínculo de frete, precisa registrar essa NF no SB e disponibilizá-la para download. O botão genérico «Validar transferências» na timeline não reflete o passo operacional.

## Decisão

- Na aba **Arquivo Cigan** (`TRANSFERENCIA_CIGAN_INICIADA`), formulário de **upload** da NF (XML, PDF ou TXT; máx. 10 MB).
- Ao enviar com sucesso: persiste `arquivo_nf_transferencia_*` no lote, executa **efetivação das transferências gerenciais** (mesma regra de `ValidarTransferenciasGerenciaisLoteAction`) e transiciona para **`AGUARDANDO_VINCULO_FRETE`**.
- GET dedicado para **baixar** a NF armazenada; permissão `captacao.lote.transferencia.validar`.
- A aba permanece visível em `AGUARDANDO_VINCULO_FRETE` para download do TXT e da NF; upload só em `TRANSFERENCIA_CIGAN_INICIADA`.
- Remove da timeline a ação **Validar transferências**; o upload na matriz substitui esse passo.

## Alternativas consideradas

- Manter botão na timeline sem upload — rejeitado; operação exige comprovante NF antes do frete.
- Upload sem efetivar transferências — rejeitado; status `AGUARDANDO_VINCULO_FRETE` pressupõe movimentações geradas ([ADR-0072](ADR-0072-vinculo-frete-pos-transferencia-lote.md)).

## Consequências

- [PLAN-0118](../plans/PLAN-0118-upload-nf-transferencia-cigan.md).
- Rota legada `validar-transferencias` pode permanecer para integrações; UI principal é o upload.
