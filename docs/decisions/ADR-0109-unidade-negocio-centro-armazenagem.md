# ADR-0109: Centro de armazenagem na unidade de negócio

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cadastro de unidades de negócio — integração Cigam

## Contexto

Cada unidade de negócio no Cigam possui um **centro de armazenagem** (código de 3 dígitos), distinto do código da unidade de negócio (pos. 602–604 do EDI, derivado do `id_cigam`).

## Decisão

- Coluna `centro_armazenagem` em `unidades_negocio`: `char(3)`, obrigatória, default **`001`** para registros legados.
- Formulário e importação: valor numérico de 1 a 3 dígitos, normalizado com zeros à esquerda (ex.: `5` → `005`).
- Histórico/auditoria registram alterações do campo.
- Importação Excel: coluna **M** opcional; vazio em linha nova usa `001`; em atualização, vazio não altera o valor atual.

## Alternativas consideradas

- Reutilizar últimos 3 dígitos do `id_cigam` — rejeitado: centro de armazenagem é código próprio no ERP.
- Campo opcional — rejeitado: toda UN possui centro no Cigam; default cobre legado.

## Consequências

- [PLAN-0109](../plans/PLAN-0109-unidade-negocio-centro-armazenagem.md).
- Integração EDI Cigan pode passar a usar `centro_armazenagem` em posições futuras; hoje o cadastro fica disponível no SB.
