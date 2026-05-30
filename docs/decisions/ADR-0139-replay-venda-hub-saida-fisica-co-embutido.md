# ADR-0139: Replay de vendas com saída física HUB e CO embutido

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** NF CAP-20260527-14-116 e [ADR-0135](ADR-0135-venda-hub-co-faturamento-embutido-custo-saida.md)

## Contexto

Vendas com saída física em HUB (`id_unidade_negocio_estoque`) e origem comercial em outra unidade não entravam no replay da unidade HUB (filtro por `id_empresa_origem`). O `aplicarSaida` do replay também recalculava `valor_custo_saida` só com PM×kg, omitindo o CO embutido da unidade de faturamento. Vendas geradas antes da correção ficaram com CO na margem ou metadados inconsistentes.

## Decisão

1. Incluir no replay da unidade vendas ativas com `id_unidade_negocio_estoque` igual à unidade reprocessada (deduplicando as já captadas por `id_empresa_origem`).
2. Em `aplicarSaida`, quando `VendaCustoOperacionalHub::coEmbutidoNoCustoSaida`, somar `valor_custo_operacional × kg` ao custo de saída replayado e não descontar CO de novo na margem.
3. Serviço de correção retroativa recalcula custos/observação conforme ADR-0135 e dispara replay na unidade HUB.
4. UI de detalhe da venda exibe **Saída física** no cabeçalho e detalhamento do CO embutido (R$/kg e total).

## Alternativas consideradas

- Cancelar e regerar NF de captação — rejeitado; operação precisa corrigir in place.
- Alterar PM global do HUB — rejeitado; viola ADR-0135.

## Consequências

- Vendas HUB legadas podem ser corrigidas via `php artisan movimentacoes:corrigir-vendas-hub-co`.
- Testes de replay e correção retroativa.
