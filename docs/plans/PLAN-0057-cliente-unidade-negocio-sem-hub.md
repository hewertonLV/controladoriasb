# PLAN-0057: Cliente vinculado apenas a unidade não-HUB (mesmo da ADR)

**ADR:** [ADR-0057](../decisions/ADR-0057-cliente-unidade-negocio-sem-hub.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Exibir select de unidades não-HUB no formulário de cliente e bloquear vínculo a HUB na validação.

## Pré-requisitos

- Coluna `is_hub` em `unidades_negocio` (ADR/migration existente).

## Passos

1. **Controller** — carregar `unidadesNegocio` com `where('is_hub', false)` em create/edit.
2. **View `_form`** — trocar input numérico por `<select>` no padrão de praças.
3. **Validação** — `Rule::exists` com filtro `is_hub = false` e mensagem clara.
4. **Testes** — form listando só não-HUB e store rejeitando HUB.

## Critério de conclusão

Tela Novo cliente mostra select apenas com unidades não-HUB; POST com HUB falha validação; testes passam.

## Riscos

- Cliente legado com HUB — mitigação: exigir troca manual na edição.
