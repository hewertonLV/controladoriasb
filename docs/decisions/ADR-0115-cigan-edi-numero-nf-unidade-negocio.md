# ADR-0115: EDI Cigan — número NF com prefixo NF e id_cigam da UN

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Registro `N` do TXT de transferência ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

O manual EDI NF Cigam define o campo **002 Série** (5 caracteres, máscara `UUUUU`) e o campo **003 Número NF** (alfanumérico, obrigatório). A operação SB passou a exigir que o número informado no arquivo identifique a unidade de negócio emissora, e não fique em branco para o Cigan numerar sozinho.

## Decisão

- **Série + número** formam um único código **`NF` + `id_cigam` da UN (HUB)**, sem zeros à esquerda no trecho numérico (ex.: `000120` → `NF120`, não `NF000120`).
- **Campo 002 — Série** (pos. 3–7, 5 caracteres): primeiros 5 caracteres desse código (ex.: `NF120` para HUB `000120`). **Não** usar `captacao_cigan_edi.serie` (`001`) no TXT de transferência.
- **Campo 003 — Número NF** (pos. 9–15, 7 caracteres): continuação do mesmo código quando passar de 5 caracteres (ex.: `883003` → série `NF883`, número `003    `); em branco se couber só na série.
- **Campo 004 — Tipo de operação** (pos. 20–24): permanece **em branco** (5 espaços); regra fiscal no Cigan.
- A UN usada é a do **HUB de origem** (`id_unidade_negocio_hub_origem`), não a de faturamento.

## Alternativas consideradas

- Deixar número NF em branco (Cigan numera pela série) — rejeitado; importação exige o código com prefixo NF.
- Usar `id_cigam` da unidade de faturamento — rejeitado; número deve refletir a UN que emite a saída de estoque (HUB).
- Preencher também o campo 004 com parte do código — rejeitado; 004 no layout é tipo de operação, não extensão do número.

## Consequências

- HUB de origem precisa de `id_cigam` cadastrado (já obrigatório para UN no TXT).
- [PLAN-0115](../plans/PLAN-0115-cigan-edi-numero-nf-unidade-negocio.md).
