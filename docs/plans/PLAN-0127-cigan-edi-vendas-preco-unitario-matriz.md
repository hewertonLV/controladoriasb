# PLAN-0127: Preço unitário no TXT Cigan de vendas

**ADR:** [ADR-0127](../decisions/ADR-0127-cigan-edi-vendas-preco-unitario-matriz.md)
**Data:** 2026-05-27
**Status:** Concluído

## Objetivo

Preencher o preço por UM da matriz no registro `I` (pos. 56–70) do arquivo de vendas Cigam.

## Pré-requisitos

- [ADR-0126](ADR-0126-arquivo-cigan-edi-vendas-faturamento.md) implementado.
- `formatarPrecoUnitarioCigam` em `CiganEdiNfTransferenciaGerador`.

## Passos

1. **Gerador** — passar `preco_venda` para `montarRegistroItem` e usar `formatarPrecoUnitarioCigam`.
2. **Validação** — exigir preço > 0 nos itens com quantidade.
3. **Testes** — unitário e pipeline de download vendas.
4. **ADR-0126** — atualizar bullet de preço.

## Critério de conclusão

- Download vendas com preço 10,00 → pos. 56–70 = `000000001000000`.
- Transferência continua com 15 espaços no preço.
- Testes verdes.

## Riscos

- Item sem preço no lote — mitigado: validação antes do download.
