# ADR-0103: Aba Arquivo Cigan TXT na matriz (transferência iniciada)

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Pipeline Lucas — transferência Cigan ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md))

## Contexto

Após **Iniciar transferência**, o lote fica em `TRANSFERENCIA_CIGAN_INICIADA`. Lucas precisa baixar um arquivo para o Cigan. O layout TXT será definido depois; a UI deve já expor o download.

## Decisão

- Na **matriz**, quando o lote estiver em `TRANSFERENCIA_CIGAN_INICIADA`, exibir aba **Arquivo Cigan** com botão de download.
- Download via GET `/lotes/{lote}/arquivo-cigan-transferencia`, permissão `captacao.lote.transferencia.iniciar`, `Content-Type: text/plain`.
- Layout EDI implementado em [ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md); snapshot CSV legado em `captacao_lote_cigan_exports` permanece separado.
- Aba indisponível fora dessa fase (404 no download).

## Alternativas consideradas

- Reutilizar CSV existente do iniciar transferência — rejeitado; operação pediu TXT com layout futuro distinto.
- Aba visível em fases posteriores — rejeitado; escopo restrito à fase atual.

## Consequências

- [PLAN-0103](../plans/PLAN-0103-matriz-aba-arquivo-cigan-txt.md).
- Próxima etapa: preencher `conteudoTxtTransferencia()` quando spec Cigan fechar.
