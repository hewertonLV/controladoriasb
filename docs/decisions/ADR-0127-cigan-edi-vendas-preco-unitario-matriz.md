# ADR-0127: EDI Cigan vendas — preço unitário da matriz

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** Arquivo TXT de vendas ([ADR-0126](ADR-0126-arquivo-cigan-edi-vendas-faturamento.md)); preço na transferência em branco ([ADR-0110](ADR-0110-cigan-edi-preco-unitario-em-branco.md))

## Contexto

No EDI NF Cigan (manual `EDI_NF_CIGAM.pdf`), o registro **I** tem o campo **005 Preço unitário** nas pos. **56–70** (máscara **N10.5**, 15 dígitos). Na **transferência**, o SB envia o campo em branco para o Cigan calcular. Na **venda**, o preço é informado na matriz de captação (`pedido_itens.preco_venda`, por unidade de medida) e deve ir no TXT.

## Decisão

- No `CiganEdiNfVendaGerador`, preencher pos. **56–70** com `formatarPrecoUnitarioCigam(preco_venda)` (valor × 100.000, 15 dígitos com zeros à esquerda), mesma regra já usada no código de transferência.
- Fonte: `preco_venda` do item do pedido com `quantidade > 0`.
- Obrigatório `preco_venda > 0` para gerar o arquivo; caso contrário, erro de validação com nome da fruta.
- **Transferência** permanece com preço em branco ([ADR-0110](ADR-0110-cigan-edi-preco-unitario-em-branco.md)).

## Alternativas consideradas

- Preço em branco nas vendas (igual transferência) — rejeitado; operação informa preço na matriz para a NF de venda.
- Preço médio do lote — rejeitado; o cadastro por célula da matriz já é o preço por UM da loja/fruta.

## Consequências

- [PLAN-0127](../plans/PLAN-0127-cigan-edi-vendas-preco-unitario-matriz.md).
- Atualizar redação de preço em [ADR-0126](ADR-0126-arquivo-cigan-edi-vendas-faturamento.md).
