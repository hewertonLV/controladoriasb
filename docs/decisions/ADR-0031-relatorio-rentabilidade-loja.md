# ADR-0031: Relatório de rentabilidade por loja (cliente)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Necessidade de visão consolidada de lucro/prejuízo por cliente

## Contexto

A operação precisa avaliar a rentabilidade de cada loja (cliente) cruzando vendas, devoluções e o custo de saída registrado na movimentação (preço médio da unidade de origem no momento da venda).

## Decisão

Criar relatório administrativo **Rentabilidade por loja** com:

- **Loja** = `id_empresa_destino` da venda (cliente).
- **Unidade de origem** = `id_empresa_origem` da venda (onde saiu o estoque e foi calculado `valor_custo_saida`).
- **Vendas ativas** (`status_registro = ATIVO`): somar `valor_nf_total`, `valor_custo_saida`, `valor_frete_rateio`, `resultado_movimentacao`, quantidades.
- **Devoluções ativas** vinculadas à venda: somar `valor_devolucao_total`, `valor_custo_devolucao`, `resultado_devolucao`, quantidades.
- **Resultado líquido** = soma dos resultados de venda + soma dos resultados de devolução (já com sinal contábil do sistema).
- **Custo médio R$/kg na venda** = `valor_custo_saida / qtd_fruta_kg` por linha agregada (média ponderada).
- Filtros: período (`data_movimentacao`), unidade origem, cliente, agrupamento (por loja ou detalhe loja+origem+fruta).
- Escopo de permissão: `relatorios.rentabilidade-loja.visualizar` e unidades permitidas ao usuário (mesma regra de `id_empresa_origem` das vendas).

## Alternativas consideradas

- Usar preço médio atual do estoque — rejeitado; distorce histórico; snapshots da venda são a fonte.
- Incluir doação/descarte no mesmo relatório — rejeitado no MVP; escopo é fluxo comercial loja.

## Consequências

- Nova permissão no `PermissionSeeder` (idempotente).
- Tela em Admin → Relatórios; sem exportação PDF no MVP.
