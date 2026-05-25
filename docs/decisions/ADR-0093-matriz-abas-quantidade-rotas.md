# ADR-0093: Abas Quantidade e Rotas na matriz de captação

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz por galpão (CD)

## Contexto

Operação precisa captar quantidades na grade clássica e, em vista separada, conferir itens por loja e vincular rota de carregamento antes de finalizar.

## Decisão

- Mesma tela `/admin/captacao/matriz` com abas **Quantidade** (matriz atual) e **Rotas**.
- Aba Rotas: uma linha por item com quantidade > 0; colunas Loja, Item, Qtd (UM), Preço, Rota.
- Rota é **por loja/pedido** (select com rowspan); opções filtradas pela carteira do lote ([ADR-0091](ADR-0091-rotas-captacao-vinculo-carteira.md)).
- Snapshot da matriz inclui `linhas_rotas` para sincronização via poll.

## Alternativas consideradas

- **Rota por item** — rejeitado; romaneio e finalização usam rota por pedido/loja.
- **Tela separada** — rejeitado; operação alterna entre captura e roteirização no mesmo lote.

## Consequências

- Endpoint `PATCH .../pedidos/{cliente}/rota`.
- [PLAN-0093](../plans/PLAN-0093-matriz-abas-quantidade-rotas.md).
