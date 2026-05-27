# ADR-0061: Realocação HUB — sempre puxar da loja de faturamento

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Refinamento da Fase 2 (ADR-0060) — venda com saída física no HUB

## Contexto

Na operação, a fruta vendida fisicamente no HUB está registrada na loja comercial por transferência HUB→loja. A realocação anterior só ocorria quando o saldo do HUB era insuficiente, permitindo vender estoque já no HUB sem reverter o efeito contábil na loja. Isso deixava o custo médio da loja distorcido, como se a fruta ainda estivesse lá.

## Decisão

- Em **toda** venda com saída física no HUB (`id_unidade_negocio_estoque` = HUB), realocar a **quantidade integral da venda** da loja comercial (unidade de faturamento), não apenas o déficit do HUB.
- Buscar transferências HUB→loja **RECEBIDA_CONFORME** (última elegível primeiro) até cobrir a quantidade da venda.
- Ao estornar na loja, debitar pelo **preço de entrada original da transferência** (não pelo preço médio consolidado atual), para desfazer o efeito no custo médio como se aquela fruta nunca tivesse entrado na loja.
- Crédito no HUB permanece ao preço de saída original da transferência, sem CO adicional.

## Alternativas consideradas

- **Realocar só o déficit do HUB (ADR-0060 inicial)** — rejeitado; não corrige custo médio da loja quando o HUB já tinha saldo.
- **Debitar na loja pelo preço médio atual** — rejeitado; não reverte fielmente o impacto da transferência indevida.

## Consequências

- `RealocacaoEstoqueHubVendaService` sempre exige transferência elegível para a qtd da venda.
- Vendas HUB sem histórico HUB→loja suficiente falham na confirmação.
- Devolução com CO usa saída física HUB (`id_unidade_negocio_estoque`), não origem comercial.
