# ADR-0015: Tela dedicada de CRUD de ICMS de frutas

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** ICMS estava apenas no formulário da fruta e na importação sem listagem centralizada.

## Contexto

Usuários precisam consultar, criar e editar ICMS por fruta/estado sem abrir cada fruta, e acessar a importação Excel no mesmo módulo.

## Decisão

Criar módulo admin em `/admin/frutas/icms` com listagem (uma linha por fruta + estado), formulário manual (fruta, estado, quatro tipos com UM) e botão de importação na listagem. Permissões: `frutas.icms.visualizar`, `frutas.icms.criar`, `frutas.icms.editar` (importação mantém as existentes).

## Alternativas consideradas

- Manter só o grid no cadastro de fruta — rejeitado; sem visão consolidada.
- Listar cada registro ENTRADA/SAIDA separado — rejeitado; confunde o usuário.

## Consequências

- Item de menu "ICMS Frutas" no cadastros.
- Edição via rota `frutas/icms/{fruta}/estados/{estado}/editar`.
