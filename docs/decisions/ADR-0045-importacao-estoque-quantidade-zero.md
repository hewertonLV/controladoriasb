# ADR-0045: Importação de estoque — saldo zero ou negativo

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Importação de posição de estoque por planilha (ADR-0036)

## Contexto

A operação precisa registrar na planilha frutas com **estoque zerado** ou **saldo negativo** (ajuste de carga inicial), alinhado à ADR 0002 (transferências podem deixar origem negativa).

## Decisão

- Coluna C (qtd na UM): aceita **qualquer valor numérico** (negativo, zero ou positivo).
- Coluna D (preço total): aceita valor numérico negativo, zero ou positivo.
- Quantidade **0** com preço total **≠ 0** continua inválido.
- Derivação: `preco_medio_kg = valor_total ÷ qtd_kg` quando `qtd_kg ≠ 0`; senão 0.
- `definirPosicaoAbsoluta` na importação aceita quantidade e preço médio kg negativos quando derivados da planilha.

## Alternativas consideradas

- Bloquear negativo só na importação — rejeitado: operação usa planilha para corrigir posição consolidada inclusive negativa.
- Exigir quantidade mínima 0,01 — rejeitado: impede zerado e negativo.

## Consequências

- Movimentação manual (entrada/saída) mantém regras próprias; apenas a **importação por planilha** define posição absoluta com saldo negativo.
- Telas de estoque podem exibir quantidades e valores negativos.
