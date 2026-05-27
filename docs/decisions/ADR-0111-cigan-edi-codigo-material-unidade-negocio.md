# ADR-0111: Código material e unidade negócio no EDI Cigan

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Layout registro I/N ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

O gerador preenchia o código material com 20 zeros à esquerda e, no registro `I`, usava o `id_cigam` da fruta no campo de unidade de negócio (pos. 656–658).

## Decisão

- **Código material** (pos. 3–22, registro `I`): **14 espaços** + **6 dígitos** do final de `frutas.id_cigam`, com zeros à esquerda quando necessário (`colocarExato`).
- **Unidade negócio** (pos. 602–604 no `N`, 656–658 no `I`): **últimos 3 dígitos** de `id_cigam` da **unidade HUB de origem** (`id_unidade_negocio_hub_origem`), com zeros à esquerda; obrigatório cadastrar `id_cigam` no HUB.
- No registro `I`, pos. 681–685 (sequência item) ficam **em branco** (espaços); o Cigan numera na importação.

## Alternativas consideradas

- 20 dígitos numéricos preenchidos com zero — rejeitado; manual Cigan usa material alinhado à direita com espaços.
- `id_cigam` da fruta na unidade negócio do item — rejeitado; campo é da UN, não do material.

## Consequências

- [PLAN-0111](../plans/PLAN-0111-cigan-edi-codigo-material-unidade-negocio.md).
- Frutas e HUB de origem precisam de `id_cigam` válido antes do download.
