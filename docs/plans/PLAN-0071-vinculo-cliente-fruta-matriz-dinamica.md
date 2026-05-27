# PLAN-0071: Vínculo cliente×fruta e colunas dinâmicas na matriz

**ADR:** [ADR-0071](../decisions/ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Cadastrar quais frutas cada loja compra e fazer a matriz de captação exibir apenas a união dinâmica dessas frutas conforme lojas entram no lote.

## Pré-requisitos

- Cadastros `Cliente` e `Fruta` existentes.
- Modelo de lote de captação (PLAN-0066 passo 1).

## Passos

1. Migration `cliente_fruta_vinculos` (`id_cliente`, `id_fruta`, unique, timestamps).
2. Model + `ClienteFrutaVinculoService` (sync conjunto de frutas por cliente).
3. Tela admin vincular frutas — busca cliente, multiselect frutas com filtro, salvar.
4. API `GET/PUT clientes/{id}/frutas-vinculadas` para app e web.
5. Serviço `ResolverColunasMatrizCaptacaoService` — união de frutas das lojas do lote.
6. Ação **adicionar loja à captação** — valida vínculos; dispara recálculo de colunas.
7. Integrar em `GET matriz` e broadcast `MatrizColunasAtualizadas`.
8. App: filtrar frutas por vínculo ao escolher cliente; validação server-side.
9. Testes — união de colunas ao adicionar 2ª loja; bloqueio loja sem vínculo.

## Critério de conclusão

- Matriz nunca exibe 500+ colunas.
- Nova loja com fruta inédita no dia → nova coluna aparece na web em tempo real.
- Vínculo editável independente da captação do dia.

## Riscos

- União muito larga se muitas lojas com mix diverso — mitigar com scroll horizontal e limite visual (aviso).

## Ordem

Executar **antes** do passo 5 (tela matriz) do [PLAN-0068](PLAN-0068-api-pedidos-painel-tempo-real.md).
