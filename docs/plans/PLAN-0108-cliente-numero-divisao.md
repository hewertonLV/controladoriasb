# PLAN-0108: Número de divisão no cadastro de cliente

**ADR:** [ADR-0108](../decisions/ADR-0108-cliente-numero-divisao.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Cadastrar o número de divisão (2 dígitos) por cliente e usá-lo no TXT Cigan.

## Passos

1. Migration `numero_divisao` em `clientes`.
2. Model, validação, formulário e auditoria.
3. `CiganEdiNfTransferenciaGerador` lê do cliente.
4. Testes `ClienteTest` e unitário Cigan.

## Critério de conclusão

Campo obrigatório no form; EDI usa valor do cliente; testes passam.
