# ADR-0057: Cliente vinculado apenas a unidade não-HUB

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Formulário Novo/Editar cliente — campo Unidade de Negócio

## Contexto

O cadastro de cliente pedia o ID numérico da unidade de negócio manualmente. Unidades HUB são entidades operacionais de faturamento/custo, não lojas físicas onde clientes são atendidos.

## Decisão

No formulário de cliente, o campo **Unidade de Negócio** passa a ser um select populado somente com unidades existentes onde `is_hub = false`. A validação de criação/edição também rejeita unidades HUB.

## Alternativas consideradas

- **Manter input numérico** — rejeitado: facilita erro de digitação e vínculo indevido a HUB.
- **Listar todas as unidades (incluindo HUB)** — rejeitado: HUB não representa loja/cliente operacional.

## Consequências

- Cadastro e edição ficam mais seguros e alinhados ao modelo operacional (loja vs HUB).
- Clientes legados eventualmente vinculados a HUB precisam ser migrados manualmente na edição, se existirem.
