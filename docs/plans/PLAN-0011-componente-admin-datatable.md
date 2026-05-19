# PLAN-0011: Componente admin datatable (mesmo da ADR)

**ADR:** [ADR-0011](../decisions/ADR-0011-componente-admin-datatable.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Disponibilizar listagem admin reutilizável com DataTables e migrar a tela de Empresas como piloto.

## Pré-requisitos

- Assets DataTables já presentes no tema (vendor).
- jQuery disponível no layout admin.

## Passos

1. **JS compartilhado** — criar `public/assets/js/admin-datatable.js` com defaults, botões e sync PDF.
2. **Componente Blade** — criar `resources/views/components/admin/datatable.blade.php`.
3. **Piloto Empresas** — atualizar index, `_table`, controller e exportação PDF (`tipo_entidade`).
4. **Testes** — ajustar `EmpresaListagemTest` e rodar suite do módulo.
5. **Migração gradual** — demais telas em tarefas futuras.

## Critério de conclusão

- Empresas exibe DataTables com copiar/imprimir/busca.
- Exportação PDF envia filtros alinhados à UI.
- Testes do módulo Empresas passam.

## Riscos

- Volume alto de registros no hub — monitorar performance; mitigar com filtros ou server-side depois.
