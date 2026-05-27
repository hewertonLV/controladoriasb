# PLAN-0118: Upload da NF de transferência na aba Arquivo Cigan

**ADR:** [ADR-0118](../decisions/ADR-0118-upload-nf-transferencia-cigan.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Permitir upload da NF de transferência Cigan na matriz, download do arquivo e transição automática para `AGUARDANDO_VINCULO_FRETE`.

## Pré-requisitos

- Lote em `TRANSFERENCIA_CIGAN_INICIADA` com HUB de origem salvo (para o TXT).

## Passos

1. **Migration** — colunas NF em `captacao_lotes`.
2. **Action + service** — armazenar arquivo e chamar efetivação + status.
3. **HTTP** — rotas POST upload e GET download; request de validação.
4. **UI** — formulário e link na aba Arquivo Cigan; remover ação na timeline.
5. **Enum** — estender visibilidade da aba.
6. **Testes** — upload, download, fluxo pipeline.

## Critério de conclusão

Upload na matriz grava NF, efetiva transferências, muda status; download funciona; testes verdes.

## Riscos

- Reupload após mudança de status — bloqueado fora de `TRANSFERENCIA_CIGAN_INICIADA`.
