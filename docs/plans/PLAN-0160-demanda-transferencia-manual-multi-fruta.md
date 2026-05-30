# PLAN-0160: Demanda manual de transferência multi-fruta

**ADR:** [ADR-0160](../decisions/ADR-0160-demanda-transferencia-manual-multi-fruta.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

CRUD de demanda manual no módulo Transferências com várias frutas, status `DEMANDA_CRIADA` e mesmo fluxo Cigam/NF após iniciar.

## Pré-requisitos

- [PLAN-0158](PLAN-0158-ciclo-demanda-transferencia-captacao.md) define padrão de INICIADO/CONCLUIDO reutilizável.

## Passos

1. **Migration** — tabela ou extensão com `origem_demanda = MANUAL|CAPTACAO`, status incluindo `DEMANDA_CRIADA`, `VINCULAR_FRETE`, linhas filhas `(fruta, qtd_um)`.
2. **Backend** — CRUD salvar rascunho; iniciar com confirmação e validação estoque; upload NF → `VINCULAR_FRETE`; excluir.
3. **UI Transferências** — formulário multi-linha (fruta + qtd); sem campo preço; descrição «criada manualmente»; botões Salvar e Iniciar transferência; após NF, UI de frete ([PLAN-0161](PLAN-0161-vincular-frete-demanda-manual.md)).
4. **Permissões** — alinhar com permissões existentes de transferência admin.
5. **Testes** — criar, editar em DEMANDA_CRIADA; iniciar trava edição; fluxo NF → VINCULAR_FRETE (sem movimentação ainda).

## Critério de conclusão

- Operador cria demanda manual com 2+ frutas e conclui via NF sem passar pela captação.

## Riscos

- Duplicar lógica Cigam — extrair serviço compartilhado com PLAN-0158.
