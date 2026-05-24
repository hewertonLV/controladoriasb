# PLAN-0079: Importação só cadastro; movimentações legado

**ADR:** [ADR-0079](../decisions/ADR-0079-importacao-apenas-cadastro-sem-movimentacoes.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Formalizar fim do uso operacional de importação de movimentações; manter código; avisos na UI.

## Passos

1. Listar rotas/menus de importação venda/transferência/estoque.
2. Banner “fluxo legado” quando pacote captação ativo (feature flag ou data corte).
3. Atualizar `docs/SISTEMA_CONTEXTO.md` § importação.
4. Não remover código nem testes nesta fase.

## Critério de conclusão

Documentação e UI orientam para captação; import movimentação ainda existe mas marcada legado.

## Ordem

Após go-live PLAN-0067 ou em paralelo à documentação de treinamento.
