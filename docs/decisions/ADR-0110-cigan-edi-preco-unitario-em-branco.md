# ADR-0110: Preço unitário em branco no EDI Cigan (transferência)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Arquivo TXT transferência HUB → galpão ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

O registro `I` do EDI NF Cigan possui o campo **Preço unitário** nas posições 56–70 (máscara N10.5, 15 caracteres). O SB passou a enviar o preço médio da matriz de captação, mas o Cigan espera o campo **vazio (espaços)** para calcular o preço na importação.

## Decisão

- No `CiganEdiNfTransferenciaGerador`, pos. 56–70 do registro `I` são preenchidas com **15 espaços** (`precoUnitarioEmBrancoCigam()`), via `colocarExato` (sem zeros).
- Não exige `preco_venda` na matriz para gerar o TXT de transferência.
- `formatarPrecoUnitarioCigam()` permanece disponível para outros layouts, mas não é usado neste export.

## Alternativas consideradas

- Enviar preço da matriz — rejeitado: Cigan rejeita ou interpreta incorretamente.
- Enviar zeros (`000000000000000`) — rejeitado: não é o formato “em branco” do manual.

## Consequências

- [PLAN-0110](../plans/PLAN-0110-cigan-edi-preco-unitario-em-branco.md).
- Atualiza a redação de preço em [ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md) quanto ao comportamento vigente.
