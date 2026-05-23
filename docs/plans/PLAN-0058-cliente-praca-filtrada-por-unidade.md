# PLAN-0058: Praça do cliente filtrada pela unidade selecionada (mesmo da ADR)

**ADR:** [ADR-0058](../decisions/ADR-0058-cliente-praca-filtrada-por-unidade.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Select de praça vazio até escolher unidade; opções filtradas dinamicamente no formulário de cliente.

## Pré-requisitos

- Lista `$pracas` no controller de clientes (já existente).

## Passos

1. **View** — praça com placeholder único; opções só quando unidade já definida (edit/validação).
2. **Template** — `<template id="cliente-pracas-opcoes">` com todas as praças e `data-unidade`.
3. **Script** — filtrar ao mudar unidade; habilitar/desabilitar select; limpar seleção ao trocar unidade.
4. **Teste** — create com select de praça disabled e template presente.

## Critério de conclusão

Novo cliente mostra praça vazia; ao selecionar unidade no browser, só praças daquela unidade aparecem; testes passam.

## Riscos

- Dependência de JS no browser — mitigação: pré-render em edit/erro de validação; validação backend inalterada.
