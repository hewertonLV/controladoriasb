# ADR-0107: Campos de contato no cadastro de cliente

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Formulário Novo/Editar cliente

## Contexto

O cadastro de cliente não permitia registrar dados de contato operacional (pessoa, telefone e e-mail). A operação precisa disso na tela manual de cadastro/edição.

## Decisão

Incluir na tabela `clientes`, opcionais:

- `contato_nome` — nome da pessoa de contato (maiúsculas, como demais textos cadastrais).
- `contato_telefone` — somente dígitos (10 ou 11 caracteres, padrão Brasil).
- `contato_email` — e-mail em minúsculas.

Exibir seção **Contato** no formulário create/edit. Histórico manual e auditoria registram alterações desses campos. Importação Excel fica fora deste escopo (layout já é por cabeçalho flexível; pode ser estendido depois).

## Alternativas consideradas

- **Tabela `cliente_contatos` (N contatos)** — rejeitado neste momento: escopo maior; operação pediu cadastro na tela principal.
- **Um único campo texto `contato`** — rejeitado: telefone e e-mail precisam de validação distinta.

## Consequências

- [PLAN-0107](../plans/PLAN-0107-cliente-campos-contato.md).
- Campos opcionais; cliente legado permanece sem contato até edição.
