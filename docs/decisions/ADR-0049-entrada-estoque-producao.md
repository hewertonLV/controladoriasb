# ADR-0049: Movimentação Entrada de Estoque (produção)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Fruta recebida direto da produção, sem NF de compra/fornecedor.

## Contexto

A operação precisa registrar entrada de fruta na unidade sem passar por compra (fornecedor, frete, ICMS de entrada). Informa fruta, quantidade em UM e preço por UM; o sistema deve criar ou incrementar estoque e recalcular preço médio como nas demais entradas.

## Decisão

Nova categoria `ENTRADA ESTOQUE` (id 8), movimentação com `status_movimentacao_id = ENTRADA`:

- **Origem e destino** = mesma unidade de negócio (produção interna).
- Campos informados: `id_fruta`, `qtd_fruta_um`, `preco_fruta_um` (preço por UM).
- `valor_nf_total` = qtd × preço UM; custo do lote em kg = `valor_nf_total / qtd_kg`.
- Preço médio consolidado: fórmula ponderada igual à compra (`Vnovo = Vprev + preco_kg_lote × qtd_kg`).
- Sem frete, custo operacional, ICMS ou fornecedor.
- Replay de linha do tempo inclui esta categoria como entrada (usa `preco_medio_fruta_kg` do lançamento, como compra).
- Cancelamento administrativo marca `CANCELADO` e reprocessa estoque da unidade/fruta.

## Alternativas consideradas

- Reutilizar importação de estoque — rejeitado: operação é movimentação auditável, não snapshot de saldo.
- Usar categoria COMPRA sem fornecedor — rejeitado: distorce relatórios e exige frete/CO.

## Consequências

- Permissões `movimentacoes.entradas-estoque.*` e item no menu Movimentações.
- Não altera fluxos de compra, transferência ou venda existentes.
