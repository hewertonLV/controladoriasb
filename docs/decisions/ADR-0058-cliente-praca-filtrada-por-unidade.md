# ADR-0058: Praça do cliente filtrada pela unidade selecionada

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Formulário Novo/Editar cliente — campo Praça

## Contexto

O select de praça listava todas as praças do sistema, independente da unidade de negócio escolhida. Isso confunde o cadastro e permite combinações inválidas antes da validação server-side.

## Decisão

No formulário de cliente, o select **Praça** inicia vazio e desabilitado até haver unidade de negócio selecionada. Ao escolher a unidade, o cliente carrega apenas praças daquela unidade (via `<template>` + JavaScript). Trocar a unidade limpa a praça selecionada.

Na edição ou após erro de validação, o servidor pré-renderiza as praças da unidade informada e o script mantém o filtro ao alterar a unidade.

## Alternativas consideradas

- **Listar todas as praças com sufixo da UN** — rejeitado: lista longa e propensa a erro.
- **Requisição AJAX por unidade** — rejeitado: dados já disponíveis na página; template local é suficiente.

## Consequências

- UX mais clara no cadastro de cliente.
- Validação `pracaPertenceUnidadeRule` permanece como garantia no backend.
