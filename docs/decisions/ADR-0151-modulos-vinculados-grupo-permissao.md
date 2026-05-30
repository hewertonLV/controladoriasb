# ADR-0151: Módulos do hub vinculados a grupos de permissão

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Hub de módulos (ADR-0146) e módulo Centralizador (ADR-0150).

## Contexto

O acesso aos módulos do hub era inferido por roles fixas e permissões avulsas (`captacao.*`, exclusão de Vendedor no Centralizador, etc.). Isso dificultava configurar quem vê cada card sem alterar código.

## Decisão

- Persistir vínculo **grupo (role) ↔ módulo** na tabela `role_app_modulos` (`role_id`, `app_modulo`).
- Tela **Administração → Grupos de Permissões**: seção **Módulos do hub** com checkboxes por `AppModulo`.
- `ModuloHubService` monta o hub pela **união** dos módulos dos grupos do usuário.
- **Programador** continua com todos os módulos via regra de sistema (`Gate::before`), formulário do grupo somente leitura.
- Permissões Spatie (`captacao.lote.visualizar`, etc.) continuam protegendo **rotas e ações**; módulos controlam apenas **entrada pelo hub** e contexto `app_modulo`.
- `RoleSeeder` define vínculos padrão idempotentes (ex.: Vendedor → Captação/Transferência/Venda; Administrador → todos).

## Alternativas consideradas

- Permissão Spatie por módulo (`modulos.captacao`) — rejeitado: mistura conceitos e polui lista de permissões operacionais.
- Manter inferência por permissão + exceções — rejeitado: regra opaca (ex. Centralizador vs Vendedor).

## Consequências

- Grupo sem módulos marcados: usuário não vê cards no hub, mesmo com permissões nas telas.
- Ajuste de acesso ao hub passa a ser operacional em Grupos de Permissões, sem deploy.
