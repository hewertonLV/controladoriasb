# PLAN-0009: Grupos de contrato com descontos mensais

**ADR:** [ADR-0009](../decisions/ADR-0009-grupos-contrato-descontos-mensais.md)
**Data:** 2026-05-18
**Status:** Concluído

## Objetivo

Implementar Grupo de Contrato com membros por competência mensal e descontos mensais em R$, removendo `desconto_contrato` de `clientes`.

## Pré-requisitos

- Especificação aprovada em `docs/superpowers/specs/2026-05-18-grupos-contrato-descontos-design.md`.
- Confirmar permissões de acesso para o novo módulo no cadastro de grupos de permissões.

## Passos

1. **Criar testes de regressão** — cobrir ausência de `desconto_contrato`, vínculo mensal de clientes e lançamento mensal de desconto.
2. **Criar migrations** — adicionar tabelas de grupos de contrato, vínculos mensais, descontos mensais e históricos; remover `desconto_contrato` de `clientes`.
3. **Criar models e relações** — implementar models, casts, soft deletes e relacionamentos.
4. **Remover campo antigo de clientes** — ajustar model, request, factory, auditoria, query, views, importação, exportação e testes.
5. **Criar serviços de domínio** — validar sobreposição de competências e registrar histórico de membros/descontos.
6. **Criar rotas e controllers** — implementar CRUD de Grupo de Contrato, membros e descontos.
7. **Criar telas** — listagem, formulário, membros, lançamentos mensais e histórico.
8. **Atualizar permissões** — adicionar permissões do módulo e garantir acesso por perfil.
9. **Verificar** — rodar testes focados e suíte relevante de clientes/grupos.

## Critério de conclusão

O sistema permite cadastrar Grupo de Contrato, vincular clientes por competência, lançar descontos mensais e consultar histórico sem depender de `clientes.desconto_contrato`.

## Riscos

- Importações antigas ainda enviarem `desconto_contrato` — mitigar removendo validação obrigatória e ignorando/ajustando layout de importação.
- Sobreposição de vínculo mensal gerar histórico incorreto — mitigar com validação de intervalo e testes específicos.
