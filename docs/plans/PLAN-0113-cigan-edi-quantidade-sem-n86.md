# PLAN-0113: Quantidade EDI Cigan máscara N8.6

**ADR:** [ADR-0113](../decisions/ADR-0113-cigan-edi-quantidade-sem-n86.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Formatar o campo quantidade (pos. 24–38) do registro `I` conforme máscara N8.6 do PDF EDI NF Cigan.

## Pré-requisitos

- ADR-0113 aceita.
- `CiganEdiNfTransferenciaGerador` e testes de layout existentes.

## Passos

1. **Formatação** — `formatarQuantidadeN86`: `round(quantidade × 1.000.000)`, 15 dígitos.
2. **Testes** — exemplos 5, 100, 62,5, 1060 e romaneio no pipeline.
3. **Docs** — ADR-0105 e comentários no gerador.

## Critério de conclusão

- Quantidade 5 gera `000000005000000` nas pos. 24–38; testes verdes.

## Riscos

- Valores muito grandes (> 8 dígitos inteiros) estouram 15 posições — mitigação: romaneio usa UM inteira em caixas.
