# ADR-0064: Galpões operacionais e três eixos na venda

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Barbalha fatura NF; galpões Recife/Maceió/Paraíba sem CNPJ; HUBs Quixeré e Missão Velha

## Contexto

Barbalha emite NF para si e para galpões sem CNPJ (Recife, Maceió, Paraíba), cada um com PM e preço de venda distintos. HUBs são centros de distribuição (estoque, PM próprio, resultado ~0 ou perda). Era necessário separar emitente fiscal, centro de resultado/margem e saída física do estoque.

## Decisão

### Galpão operacional

- Flag `is_galpao_operacional` em `unidades_negocio`: `possui_estoque = true`, `is_hub = false`, CNPJ opcional. Emissão de NF é controlada por `emite_nota_fiscal` — ver [ADR-0082](ADR-0082-galpao-operacional-emite-nota-fiscal.md) (ex.: CD Barbalha galpão + NF).
- Galpões: Recife, Maceió, Paraíba (cadastro manual).

### Clientes

- Sempre `id_unidade_negocio = Barbalha` (emitente fiscal) + `id_praca` (RECIFE, MACEIÓ, PARAÍBA, BARBALHA/MATRIZ).
- Galpão **não** é UN do cliente.

### Três eixos na venda manual

| Eixo | Campo | Função |
|------|-------|--------|
| Faturamento (NF) | `id_empresa_origem` / `id_unidade_negocio_faturamento` | CNPJ, ICMS, emitente |
| Centro de resultado | `id_unidade_negocio_centro_resultado` (nullable → faturamento) | PM debitado, CO na margem, relatórios |
| Saída física | `id_unidade_negocio_estoque` (nullable → centro/faturamento) | Onde debita estoque quando ≠ centro (ex.: HUB) |

### CO na margem

- **Centro = galpão:** CO vigente do **galpão**.
- **Saída HUB + centro = loja faturamento** (venda direta CD): CO da **loja** (ADR-0063).
- **Centro = loja, saída = mesma loja:** CO = 0 (já no PM).
- **Unidade de produção, saída local:** ADR-0050 (CO do HUB selecionado).

### HUB (Quixeré, Missão Velha)

- `is_hub = true`, controlam estoque; **não** acumulam lucro — PM na saída, resultado 0 ou negativo (perda).

### Realocação ADR-0061

- Só quando saída física = HUB **e** centro de resultado = **mesma** unidade de faturamento (loja).
- Venda com centro = galpão: debita PM do galpão; **sem** realocação Barbalha↔HUB.

### Relatórios

- Rentabilidade agrupa margem por **centro de resultado** (galpão ou loja), não só por `id_empresa_origem`.

## Alternativas consideradas

- Galpão como praça sem UN/estoque — rejeitado: PM separado por galpão.
- Galpão como UN do cliente — rejeitado: emitente fiscal único Barbalha.
- Um campo só para estoque — rejeitado: não separa HUB físico vs galpão gerencial.

## Consequências

- Migration: `is_galpao_operacional`, `id_unidade_negocio_centro_resultado`.
- Formulário venda: faturamento + centro resultado + saída física.
- ADR-0063 permanece para venda direta HUB→loja faturamento.
