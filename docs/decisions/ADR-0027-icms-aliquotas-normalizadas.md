# ADR-0027: ICMS em `fruta_icms_aliquotas` (fruta × estado × operação × procedência × escopo)

**Data:** 2026-05-20
**Status:** Aceito
**Contexto:** Redesenho do cadastro de ICMS com entrada/saída por estado, base em kg na entrada, percentual na venda PE, e procedência nacional/internacional.

## Contexto

O modelo `fruta_icms` (duas linhas ENTRADA/SAIDA com colunas `icms_nacional`, `icms_venda_importada`, UMs `KG`/`UM`/`PCT`) misturava semânticas. O negócio exige:

- ICMS por **fruta** e **estado**;
- Cada estado cobra na **entrada** ou na **saída** (`estados.icms_cobra_em`);
- **Procedência** da fruta: NACIONAL ou INTERNACIONAL;
- Na **venda**, **escopo** geográfico: DENTRO_ESTADO ou FORA_ESTADO (UF do cliente vs UF da unidade de faturamento);
- Entrada: valor em **R$/kg** (`tipo_valor = VALOR_POR_KG`);
- Saída (PE): **percentual** sobre `valor_nf` (`tipo_valor = PERCENTUAL`);
- Até **quatro** alíquotas de venda por fruta/estado (nacional/internacional × dentro/fora).

## Decisão

1. Tabela **`fruta_icms_aliquotas`** com chave única `(fruta_id, id_estado, operacao, procedencia, escopo_venda)`.
2. Campos: `tipo_valor` (`VALOR_POR_KG` | `PERCENTUAL`), `valor` (decimal).
3. `escopo_venda` **NULL** apenas para `ENTRADA`; obrigatório `DENTRO_ESTADO` | `FORA_ESTADO` para `SAIDA`.
4. `frutas.procedencia` (`NACIONAL` | `INTERNACIONAL`) define qual alíquota de **saída** usar no cálculo.
5. `estados.icms_cobra_em` (`ENTRADA` | `SAIDA` | `NENHUM`) orienta UI e validação.
6. Histórico: `fruta_icms_historicos.aliquotas` (JSON) com snapshot das 6 chaves de formulário.
7. Migração de `fruta_icms` legado; tabela antiga removida após backfill.
8. Importação planilha: colunas C–H = entrada kg + quatro % de venda (nacional/internacional × dentro/fora); modo legado 10/11 colunas mantido via adaptador.
9. Cálculo venda: `valor_nf × (valor / 100)` com alíquota `(procedencia da fruta, escopo UF cliente vs faturamento)`.

## Alternativas consideradas

- Manter `fruta_icms` e só renomear colunas — rejeitado; não modela 4 combinações de venda com internacional.
- Uma linha por fruta/estado com 6 colunas — rejeitado; pior para extensão e histórico linha a linha.

## Consequências

- ADR-0013/0014/0025 parcialmente substituídos para persistência; regras de negócio CE/PE preservadas.
- ADR-0026 (coluna K) permanece no adaptador de importação legado.
- Abacaxi (ADR-0006): valores em UM na planilha legada convertidos para R$/kg na importação usando `kg_por_unidade_medicao`.
