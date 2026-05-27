# PLAN-0115: Número NF EDI com NF + id_cigam da UN

**ADR:** [ADR-0115](../decisions/ADR-0115-cigan-edi-numero-nf-unidade-negocio.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Gerar o campo 003 (Número NF, 7 pos.) do registro `N` como `NF` + 3 dígitos do `id_cigam` do HUB de origem.

## Pré-requisitos

- ADR-0115 aceita.
- `CiganEdiNfTransferenciaGerador` com HUB obrigatório no lote.

## Passos

1. **Gerador** — `numeroNotaFiscalCigam($idCigamHub)` e uso em `montarRegistroNota`.
2. **Testes** — unitário + feature download TXT.
3. **ADR-0105** — atualizar descrição do número NF.
4. **Verificação** — `php artisan test --filter=CiganEdi`.

## Critério de conclusão

TXT do registro `N` nas pos. 9–15 contém `NF` + 3 dígitos da UN HUB (7 caracteres); testes verdes.

## Riscos

- Divergência se o Cigan exigir exatamente 7 posições (manual «007») — ajustar faixa 9–15 se a importação falhar.
