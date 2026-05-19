# ADR-0022: Desabilitar cadastro público (register)

**Data:** 2026-05-19
**Status:** Aceito

## Decisão

Rotas `GET/POST /register` removidas. Novos usuários só via módulo admin **Usuários** (permissão `usuarios.criar`). Tela de login informa isso em texto, sem link de cadastro.

## Consequências

- `RegisteredUserController` e view `auth/register.blade.php` ficam órfãos (podem ser removidos depois).
- Tentativa de acesso a `/register` retorna 404.
