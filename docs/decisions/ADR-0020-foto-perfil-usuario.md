# ADR-0020: Foto de perfil do usuário

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Avatar fixo (`avatar-1.jpg`) no menu; necessidade de foto individual por usuário.

## Contexto

Cada usuário deve poder enviar e atualizar sua foto no **Meu Perfil**, exibida na sidebar e topbar.

## Decisão

- Coluna `users.avatar_path` (caminho relativo no disco `public`).
- Upload em `storage/app/public/avatars/{user_id}/` via `UserAvatarService`.
- Formatos: JPG, PNG, WebP; máximo 2 MB.
- Sem foto: mantém imagem padrão `assets/images/users/avatar-1.jpg`.
- Upload e remoção apenas pelo próprio usuário em `/profile` (não pelo cadastro admin nesta entrega).
- Ao substituir ou remover, o arquivo anterior é apagado do disco.

## Alternativas consideradas

- **BLOB no MySQL** — rejeitado: pior para backup e cache de CDN.
- **Disco `local` (privado) + rota autenticada** — rejeitado: mais código; `public` + `storage:link` é suficiente para fotos de perfil internas.
- **Admin editar foto de outro usuário** — fora do escopo inicial; só perfil próprio.

## Consequências

- Executar `php artisan storage:link` no ambiente (Docker incluído se já houver no entrypoint).
- Fotos acessíveis por URL pública em `/storage/avatars/...` (aceitável para intranet).
