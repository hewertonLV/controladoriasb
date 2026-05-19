# ADR-0017: CRUD admin de estados com inativação por soft delete

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Estados (ICMS) existiam só via seeder; faltava cadastro manual.

## Decisão

1. Tela admin `/admin/estados` com listagem DataTables, criar, editar.
2. Inativar/reativar via `deleted_at` (soft delete já existente na tabela).
3. Bloquear inativação se houver vínculo em unidade de negócio, fornecedor ou `fruta_icms`.
4. Permissões: `estados.visualizar`, `criar`, `editar`, `inativar`, `reativar`.

## Alternativas consideradas

- Coluna `ativo` — rejeitado; soft delete já é o padrão da tabela.
- Histórico de auditoria dedicado — rejeitado no escopo inicial (referência ICMS, baixa frequência de mudança).

## Consequências

- Formulários de fornecedor/unidade continuam listando apenas estados ativos (scope padrão).
- Route model binding usa `withTrashed` para editar/reativar inativos.
