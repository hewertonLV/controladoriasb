# PLAN-0081: Contrato API app mobile (fase posterior)

**ADR:** [ADR-0081](../decisions/ADR-0081-aplicativo-mobile-captacao-contrato-api.md)
**Data:** 2026-05-29
**Status:** Em andamento

## Objetivo

Garantir API estável documentada para o app; Sanctum e telas nativas ficam para sprint dedicada.

## Passos

1. Manter rotas atuais em `routes/api.php` como referência.
2. Ao iniciar app: `composer require laravel/sanctum`, `POST auth/login`, middleware `auth:sanctum`.
3. Implementar endpoints de catálogo (clientes, frutas, rotas).
4. Testes de contrato em `tests/Feature/Api/Captacao/`.

## Critério de conclusão

App consegue abrir lote, listar clientes/frutas e gravar pedido com token.
