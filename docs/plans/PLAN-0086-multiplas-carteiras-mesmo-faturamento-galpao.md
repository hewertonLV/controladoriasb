# PLAN-0086: Várias carteiras com o mesmo faturamento e galpão

**ADR:** [ADR-0086](../decisions/ADR-0086-multiplas-carteiras-mesmo-faturamento-galpao.md)
**Data:** 2026-05-25
**Status:** Concluído

## Objetivo

Permitir cadastrar e editar várias carteiras com o mesmo par faturamento + galpão, sem erro 500/validação.

## Pré-requisitos

- Tabela `captacao_carteiras` existente (ADR-0084).

## Passos

1. **Migration** — remover `cap_cart_fat_galp_uq`; adicionar índice `cap_cart_fat_galp_ix`.
2. **Domínio** — remover `validarParUnidadesUnico`; ajustar `garantirCarteira` (ordem estável quando várias existem).
3. **HTTP** — retirar chamadas de unicidade no controller de carteiras.
4. **Docs** — ADR-0086; revisar bullet em ADR-0084.
5. **Testes** — permitir duplicata de par no store/update.

## Critério de conclusão

- Duas carteiras com mesmo faturamento e galpão persistem no banco.
- Testes `CaptacaoCarteiraTest` verdes.

## Riscos

- Romaneio manual sem `id_captacao_carteira` pode associar à carteira de menor id — mitigar na UI futura com seletor de carteira.
