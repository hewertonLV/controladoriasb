# ADR-0082: Galpão operacional e emissão de NF (CD Barbalha)

**Data:** 2026-05-29
**Status:** Aceito
**Contexto:** Cadastro de unidades; [ADR-0064](ADR-0064-galpoes-operacionais-venda-tres-eixos.md)

## Contexto

`is_galpao_operacional` marca centro de resultado regional com estoque gerencial (Recife, Maceió, CD Barbalha). A UI dizia que galpão “não fatura NF”, o que vale para galpões atendidos pela matriz (ex.: Recife), mas **CD Barbalha** é galpão **e** emite NF no próprio CNPJ.

## Decisão

- Novo flag **`emite_nota_fiscal`** em `unidades_negocio`.
- **`is_galpao_operacional`:** centro de resultado / captação por galpão / PM regional — independente de NF.
- **`emite_nota_fiscal`:** pode ser origem comercial (venda manual) e unidade de **faturamento** na captação.
- Padrão na migration: galpões existentes `emite_nota_fiscal = false`; demais unidades `true`.
- Exemplos: Recife (galpão, não emite); CD Barbalha (galpão, emite); Barbalha matriz (não galpão, emite).

## Alternativas consideradas

- Remover só o texto da tela — rejeitado; CD Barbalha continuaria bloqueado no código.
- Inferir emissão só por CNPJ — rejeitado; operação precisa marcar explicitamente.

## Consequências

- Formulário UN: dois switches com textos distintos.
- Venda e captação filtram faturamento por `emite_nota_fiscal`, não por “não ser galpão”.
