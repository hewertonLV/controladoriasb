# PLAN-0003: Uso da aba "BASE" como fonte canônica da planilha de materiais

**ADR:** [ADR-0003](../decisions/ADR-0003-aba-base-planilha-materiais.md)
**Data:** 2026-05-17
**Status:** Concluído

## Objetivo

Reescrever `planilhas/MATERIAIS V2.xlsx` contendo apenas a aba BASE, sem duplicatas e com as colunas de ICMS no cabeçalho.

## Pré-requisitos

- Arquivo original `planilhas/MATERIAIS V2.xlsx` acessível
- Python com `openpyxl` instalado

## Passos

1. **Extrair dados da aba BASE** — ler sheet3 do arquivo original via `zipfile` + `xml.etree`
2. **Remover duplicatas** — manter apenas a primeira ocorrência de cada `CODIGO MATERIAL`
3. **Adicionar colunas ICMS** — incluir cabeçalhos `ICMS_EX_COMPRA`, `ICMS_NA_COMPRA`, `UM_ICMS`, `ICMS_VENDA` nas colunas E–H
4. **Salvar** — sobrescrever o arquivo com uma única aba chamada `BASE`
5. **Validar** — confirmar: 1 aba, 44 linhas de dados, cabeçalhos corretos

## Critério de conclusão

Arquivo com exatamente 1 aba (`BASE`), 45 linhas (1 cabeçalho + 44 dados), colunas E–H presentes no cabeçalho.

## Riscos

- Perda dos dados das abas `T.O` e `MATERIAIS` — salvar backup antes de sobrescrever se necessário para consulta fiscal futura
