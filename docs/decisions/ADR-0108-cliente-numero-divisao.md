# ADR-0108: Número de divisão no cadastro de cliente

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cadastro de cliente e exportação Cigan EDI ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

No layout EDI NF Cigan, o campo **Divisão** ocupa as posições 599–600 do registro `N` (2 dígitos). O sistema usava apenas o default global `captacao_cigan_edi.divisao`. Na operação, cada cliente possui seu próprio número de divisão no Cigam.

## Decisão

- Coluna `numero_divisao` em `clientes`: `char(2)`, obrigatória, default `10` para registros legados.
- Formulário manual: campo **Número de divisão** (2 dígitos numéricos, zeros à esquerda ao salvar).
- Arquivo Cigan: pos. 599–600 do registro `N` usa `numero_divisao` do **cliente principal** da unidade de faturamento; se ausente, fallback em `config('captacao_cigan_edi.divisao')`.
- Importação Excel de clientes: fora deste escopo (coluna pode ser adicionada depois).

## Alternativas consideradas

- Manter só config global — rejeitado: não reflete cadastro por cliente no Cigam.
- Campo na unidade de negócio — rejeitado: divisão é atributo do cliente no ERP.

## Consequências

- [PLAN-0108](../plans/PLAN-0108-cliente-numero-divisao.md).
- Clientes existentes recebem `10` na migration até serem editados.
