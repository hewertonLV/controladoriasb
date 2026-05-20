# ADR-0030: Dashboard básica filtrada por unidade do usuário

**Data:** 2026-05-20
**Status:** Aceito
**Contexto:** Painel inicial com dados reais, respeitando unidades vinculadas ao usuário.

## Contexto

A rota `/dashboard` exibia placeholders fixos. Usuários com escopo parcial de unidades precisam ver apenas cadastros e movimentações das unidades permitidas.

## Decisão

- Versão 1: totais (clientes, veículos, praças, estoques, movimentações) e tabelas simples, sem gráficos financeiros.
- Escopo via `UnidadeNegocioAccessService` (mesma regra de movimentações): administrador/programador vê tudo; demais usuários só unidades em `unidade_negocio_user`.
- Movimentações contabilizadas quando origem/destino (empresa) ou unidade de faturamento/retorno pertence ao escopo.

## Alternativas consideradas

- Endpoint JSON separado — rejeitado na v1: página server-rendered é suficiente.
- Incluir cadastros globais (frutas, fornecedores) — rejeitado: não são por unidade e distorcem o escopo operacional.

## Consequências

- Dashboard evolui depois com KPIs financeiros sem alterar a regra de escopo.
- Usuário sem unidades vê totais zerados e mensagem de escopo vazio.
