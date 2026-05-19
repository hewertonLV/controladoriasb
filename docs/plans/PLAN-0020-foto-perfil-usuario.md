# PLAN-0020: Foto de perfil do usuário

**ADR:** [ADR-0020](../decisions/ADR-0020-foto-perfil-usuario.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir upload de foto de perfil e exibir no menu e na página Meu Perfil.

## Pré-requisitos

- `php artisan migrate`
- `php artisan storage:link` (se ainda não existir)

## Passos

1. Migration `avatar_path` em `users`
2. `UserAvatarService` + accessor `avatar_url` no model
3. `ProfileUpdateRequest` e `ProfileController` com upload/remoção
4. Formulário de perfil com `enctype` e preview
5. Componente `<x-user-avatar>` na sidebar e topbar
6. Testes em `ProfileTest`

## Critério de conclusão

Usuário envia foto em Meu Perfil; imagem aparece no menu após salvar; remoção restaura avatar padrão.

## Riscos

- Link simbólico `storage` ausente — documentar no deploy.
