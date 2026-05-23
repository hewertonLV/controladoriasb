# PLAN-0060: Venda — origem comercial, saída física e realocação HUB

**ADR:** [ADR-0060](../decisions/ADR-0060-venda-origem-comercial-saida-fisica-hub.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir venda com NF da loja e baixa de estoque no HUB, com realocação automática quando o saldo contábil estiver na loja.

## Pré-requisitos

- ADR-0060 aceita
- Worker importação atualizado após deploy

## Passos

1. **Migration** — `id_unidade_negocio_estoque` nullable FK em `movimentacoes`.
2. **VendaMovimentacaoService** — resolver comercial/estoque/faturamento; CO=0 se estoque HUB; gravar campo novo.
3. **RealocacaoEstoqueHubVendaService** — localizar transferência elegível, estorno parcial loja, restauração HUB sem CO, replay.
4. **Requests + cancelamento** — validar origem não-HUB; estoque opcional; replay na unidade de estoque.
5. **UI manual** — origem comercial + saída física; remover faturamento condicional HUB.
6. **Importação** — reverter ADR-0059; origem comercial + saída física por grupo NF.
7. **Testes** — venda HUB físico/loja comercial; realocação automática; regressão produção CO.

## Critério de conclusão

- Venda Fortaleza + estoque HUB debita HUB, faturamento Fortaleza, CO margem 0.
- Cenário transferência HUB→loja + venda realoca e vende com saldos consistentes.
- Testes de venda passando.

## Riscos

- Realocação parcial em transferência com frete — recalcular rateio se necessário.
- Saldo na loja misturado (compra + transferência) — limitar realocação à qtd da transferência elegível.
