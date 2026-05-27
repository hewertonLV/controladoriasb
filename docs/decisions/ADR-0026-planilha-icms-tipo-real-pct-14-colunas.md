# ADR-0026: Planilha de ICMS — coluna K "tipo do estado" (REAL/PCT) e layout de 11 colunas

**Data:** 2026-05-20
**Status:** Aceito
**Contexto:** Importação de ICMS via planilha Excel (`planilhas/icms.xlsx`)

## Contexto

O layout original de 10 colunas (ADR-0014) usava `PCT` como valor da coluna UM de venda para sinalizar que o valor é percentual (ex.: PE venda). O problema era que as colunas de UM de **compra** (D e F) também recebiam `PCT` nas linhas de PE — o validador rejeita PCT na entrada, causando falha de importação para todos os 44 registros de PE. A causa raiz é que o layout não distingue o **tipo do cálculo do estado** (percentual ou real) das unidades de medida das colunas individuais.

## Decisão

Manter o layout A–J **intacto** (ADR-0014) e adicionar uma única coluna **K — `tipo_estado`** (`REAL` | `PCT`) que declara como o estado calcula o ICMS:

| Col | Campo | Valores aceitos |
|-----|-------|-----------------|
| A | id_cigam da fruta | — |
| B | estado | ID, sigla ou nome |
| C | ICMS compra nacional | valor numérico |
| D | UM compra nacional | `KG` / `UM` |
| E | ICMS compra exterior | valor numérico |
| F | UM compra exterior | `KG` / `UM` |
| G | ICMS venda dentro do estado | valor numérico |
| H | UM venda dentro do estado | `KG` / `UM` / `PCT` |
| I | ICMS venda fora do estado | valor numérico |
| J | UM venda fora do estado | `KG` / `UM` / `PCT` |
| **K** | **tipo do estado** | `REAL` / `PCT` |

Regras de mapeamento no normalizer:
- `K = PCT` → os valores de venda (G/I) são percentuais; o normalizer força `um_venda_* = PCT`, independente do que estiver em H/J.
- `K = REAL` → valores são R$ por unidade; UMs de H/J são usadas como informadas.
- `K ausente` (10 colunas, modo legado) → comportamento anterior preservado: `validacaoService` infere PCT para PE quando valor > 0.

Compra (D/F): **sempre `KG` ou `UM`**, independentemente de K. PCT nunca é válido na entrada.

O banco de dados (`fruta_icms`, colunas `um_icms_*`) **não muda**.

## Alternativas consideradas

- **14 colunas com tipo por par** — mais expressivo, mas duplica colunas desnecessariamente; o tipo é uma propriedade do estado, não de cada coluna individualmente.
- **Manter 10 colunas e corrigir só D/F de PE para KG** — resolve o bug mas não documenta explicitamente o tipo de cálculo do estado, exigindo que o usuário saiba de cor que PE usa percentual.
- **Coluna K apenas para venda** — equivalente à decisão tomada, pois K só afeta venda; mas o nome "tipo do estado" comunica melhor a intenção.

## Consequências

- `FrutaIcmsPlanilhaNormalizer` lê K (índice 10) opcionalmente: se `PCT`, sobrescreve `umVendaImportada` e `umVendaNacional` com `PCT` antes de chamar `validacaoService`.
- Planilha `planilhas/icms.xlsx`: 11 colunas; CE → `K=REAL`, PE → `K=PCT`; colunas D/F de PE corrigidas de `PCT` → `KG`.
- Testes cobrem K=REAL, K=PCT, K inválido, K ausente (modo legado).
- ADR-0014 permanece válida para a semântica das colunas A–J; este ADR adiciona apenas a coluna K.
