# PLAN-0107: Campos de contato no cadastro de cliente

**ADR:** [ADR-0107](../decisions/ADR-0107-cliente-campos-contato.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir cadastrar nome, telefone e e-mail de contato na tela de cliente.

## Passos

1. Migration `contato_nome`, `contato_telefone`, `contato_email`.
2. Model, validação e normalização nos requests.
3. Seção no `_form.blade.php` e histórico.
4. Testes em `ClienteTest`.

## Critério de conclusão

Create/update persistem contato; histórico audita alterações; testes passam.

## Riscos

- Telefone internacional — mitigação: validar 10–11 dígitos (Brasil); ampliar depois se necessário.
