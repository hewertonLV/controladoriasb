# ADR-0170: Importação vínculo loja×fruta por ID CIGAM

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Importação de vínculos ([ADR-0085](ADR-0085-importacao-vinculo-fruta-loja-planilha.md), [ADR-0071](ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md))

## Contexto

A operação cadastra clientes e frutas no CIGAM com códigos numéricos estáveis. Importar por razão social/nome gerava ambiguidade e exigia digitação imprecisa. A tela de **Clientes** precisa do mesmo fluxo de importação em massa já existente em Frutas por loja.

## Decisão

- Planilha Excel: **coluna A** = `id_cigam` do cliente; **coluna B** = `id_cigam` da fruta; linha 1 = cabeçalho; **um vínculo por linha**; várias lojas e frutas no mesmo arquivo.
- Normalização: mesma regra de cadastro (`TextoCadastro::normalizarIdCigamAteSeisDigitos`).
- Resolução: cliente pela unidade de faturamento selecionada + `id_cigam`; fruta global por `id_cigam`.
- Comportamento aditivo, preview assíncrono e confirmação permanecem como [ADR-0085](ADR-0085-importacao-vinculo-fruta-loja-planilha.md).
- Entrada na UI: botão **Importar vínculo loja×fruta** na listagem de Clientes e fluxo equivalente em Frutas por loja.
- Modelo: `planilhas/fruta_loja_vinculo.xlsx`.
- Permissão: `captacao.cliente_fruta.vincular`.

## Alternativas consideradas

- **Manter importação só por nome (ADR-0085 original)** — rejeitada; operação usa ID CIGAM.
- **Dois layouts (nome ou CIGAM)** — rejeitada; um layout reduz suporte e erros.

## Consequências

- [PLAN-0170](../plans/PLAN-0170-importacao-vinculo-fruta-loja-id-cigam.md).
- Atualiza [ADR-0085](ADR-0085-importacao-vinculo-fruta-loja-planilha.md) no layout da planilha.
