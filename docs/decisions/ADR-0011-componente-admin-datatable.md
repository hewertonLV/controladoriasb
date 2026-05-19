# ADR-0011: Componente admin datatable (DataTables client-side)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Padronizar listagens admin com busca, copiar e imprimir sem duplicar scripts em cada tela.

## Contexto

O projeto tinha dois padrões: `<x-admin.data-table>` (AJAX + paginação servidor) e DataTables inline em fornecedores. A equipe optou pelo formato DataTables em todas as telas, começando por Empresas.

## Decisão

Criar `<x-admin.datatable>` + `public/assets/js/admin-datatable.js` com:

- DataTables client-side (busca, ordenação e paginação no navegador);
- botões Copiar/Imprimir padrão;
- campos ocultos `data-table-*` sincronizados para exportação PDF assíncrona;
- filtros de linha via `data-table-filter` + atributos `data-*` nas `<tr>` (ex.: `data-tipo-entidade`, `data-status`).

Carga inicial da listagem: `Query::get()` no controller (sem partial AJAX).

## Alternativas consideradas

- Manter `<x-admin.data-table>` e só mudar CSS — não unifica copiar/imprimir com DataTables.
- DataTables `serverSide: true` — mais complexo; adiado até listas muito grandes.
- Copiar o bloco inteiro de fornecedores em cada view — alto custo de manutenção.

## Consequências

- Telas migradas deixam de usar paginação AJAX; testes de listagem validam HTML inicial e parâmetros de URL.
- Listas muito grandes podem exigir limite ou server-side no futuro.
- Demais módulos devem migrar para o mesmo componente gradualmente.
