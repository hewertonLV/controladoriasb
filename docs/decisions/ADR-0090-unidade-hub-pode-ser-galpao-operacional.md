# ADR-0090: Unidade HUB pode ser galpão operacional

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Cadastro de unidades de negócio ([ADR-0064](ADR-0064-galpoes-operacionais-venda-tres-eixos.md))

## Contexto

Operação precisa marcar a **mesma** unidade física como **HUB** (saída física, transferências gerenciais) e **galpão operacional** (carteira de captação, centro de resultado, PM regional). A validação anterior impedia `is_hub` e `is_galpao_operacional` simultâneos.

## Decisão

- **Permitido** `is_hub = true` e `is_galpao_operacional = true` na mesma unidade.
- **Mantido:** galpão operacional exige `possui_estoque = true`; HUB não pode `emite_nota_fiscal = true`.
- **Centro de resultado** na venda: unidade só é rejeitada como centro se for HUB **sem** flag de galpão; HUB+galpão entra na lista de centros e pode ser selecionado como centro de resultado.
- **Listagem de centros** (`VendaMovimentacaoService`): inclui todo `is_galpao_operacional`; demais unidades com estoque continuam exigindo `is_hub = false`.

## Alternativas consideradas

- **Duas UNs cadastradas** para o mesmo CD — rejeitado; duplicação e divergência de PM.
- **Manter exclusão mútua** — rejeitado; requisito operacional explícito.

## Consequências

- Removida validação no formulário, importação Excel e trait `ValidatesUnidadeNegocioAttributes`.
- Atualizar [ADR-0064](ADR-0064-galpoes-operacionais-venda-tres-eixos.md) mentalmente: galpão não exige mais `is_hub = false`.
