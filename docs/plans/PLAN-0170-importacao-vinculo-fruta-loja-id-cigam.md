# PLAN-0170: Importação vínculo loja×fruta por ID CIGAM

**ADR:** [ADR-0170](../decisions/ADR-0170-importacao-vinculo-fruta-loja-id-cigam.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Importar vínculos cliente×fruta por planilha com ID CIGAM (A/B), com botão na tela Clientes e modelo Excel em `planilhas/`.

## Pré-requisitos

- Infra de importação [PLAN-0085](PLAN-0085-importacao-vinculo-fruta-loja-planilha.md).

## Passos

1. **Normalizer + processor** — resolver por `id_cigam` cliente/fruta.
2. **Rotas Clientes** — reutilizar controller com URLs de retorno distintas.
3. **UI** — botão na listagem Clientes; atualizar textos da importação.
4. **Planilha modelo** — `planilhas/fruta_loja_vinculo.xlsx`.
5. **Testes** — processor e tela Clientes.

## Critério de conclusão

- Upload com id_cigam gera preview e confirma vínculos.
- Botão visível em `/admin/clientes` para quem tem permissão.
- Testes PHPUnit passando.

## Riscos

- Cliente de outro faturamento com mesmo id_cigam — mitigação: `id_cigam` único global + filtro por faturamento na resolução.
