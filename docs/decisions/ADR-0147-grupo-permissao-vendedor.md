# ADR-0147: Grupo de permissão Vendedor

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Hub de módulos (ADR-0146) e perfil operacional de vendedor.

## Contexto

Vendedores precisam de um grupo Spatie (`Role`) dedicado, distinto de Administrador/Controladoria, com escopo limitado aos módulos operacionais.

## Decisão

- Criar role **`Vendedor`** em `App\Enums\Roles`.
- O seeder (`RoleSeeder`) atribui permissões padrão via `Permissions::permissoesGrupoVendedor()`:
  - **Captação:** todas as permissões do grupo Captação em `Permissions::groups()`.
  - **Transferência:** visualizar, criar, editar, receber, reenviar e cancelar (sem importação nem cancelamento admin).
  - **Venda:** visualizar, criar e editar (sem importação nem cancelamento admin).
- Usuário com role `Vendedor` vê no hub os três módulos operacionais e **não** vê Administrador.
- Permissões extras podem ser concedidas manualmente em Grupos de Permissão; o seeder só adiciona as padrão (idempotente).

## Alternativas consideradas

- Derivar acesso só por permissões avulsas — rejeitado: dificulta atribuição em massa e auditoria de perfil.
- Incluir importação/cancelamento admin — rejeitado: ações de backoffice ficam fora do escopo vendedor.

## Consequências

- Novos usuários vendedores recebem o grupo `Vendedor` na tela de usuários.
- Evolução de escopo do vendedor altera `permissoesGrupoVendedor()` e este ADR.
