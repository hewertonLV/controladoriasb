# ADR-0106: Código do cliente na unidade de negócio

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cadastro de unidades de negócio — integração CIGAM e captação

## Contexto

Cada unidade de negócio (loja/faturamento) possui um código próprio no CIGAM (`id_cigam` da UN). Para exportações e cadastro operacional, é necessário registrar também o **código CIGAM do cliente** vinculado àquela unidade (o mesmo `id_cigam` do cadastro de clientes).

Já existe relação inversa: vários `clientes` apontam para `id_unidade_negocio`. Falta indicar, na UN, qual cliente representa a loja principal.

## Decisão

- Adicionar `id_cliente` (FK nullable, unique) em `unidades_negocio`, referenciando `clientes.id`.
- Na UI, o campo é **Código do cliente** (exibe e aceita o `id_cigam` do cliente via select dos clientes da própria unidade).
- Validação: o cliente escolhido deve ter `id_unidade_negocio` igual ao `id` da unidade em edição; em criação manual o vínculo só pode ser definido na edição (após existirem clientes na UN).
- Importação Excel: coluna **L** opcional com o `id_cigam` do cliente; na atualização resolve `id_cliente` se o cliente existir e pertencer à UN; em linhas novas a coluna é ignorada (vincular após cadastrar clientes na UN).
- Histórico e auditoria registram `id_cliente` (snapshot com `codigo_cliente` = `id_cigam` do cliente).

## Alternativas consideradas

- **Coluna texto `codigo_cliente` sem FK** — rejeitado: duplicaria dado e desincronizaria do cadastro de clientes.
- **Derivar automaticamente do único cliente da UN** — rejeitado: várias lojas por UN de faturamento; escolha deve ser explícita.

## Consequências

- [PLAN-0106](../plans/PLAN-0106-unidade-negocio-codigo-cliente.md).
- Planilha de importação ganha coluna L; filtro de leitura passa a A:L.
- Um cliente só pode ser “principal” de uma UN (unique em `id_cliente`).
