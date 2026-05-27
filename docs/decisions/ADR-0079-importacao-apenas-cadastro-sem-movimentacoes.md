# ADR-0079: Importação só de cadastro; movimentações deixam de ser importadas

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Virada Controladoria como origem ([PACOTE-0066](../pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md)); PDF regra 12

## Contexto

A estratégia passa a ser **captação e lançamentos no SB** (pedidos, romaneios, transferências e vendas geradas no sistema). Importações Excel de **movimentações** (vendas, transferências, estoques operacionais) deixam de ser o fluxo operacional. O código de importação legado **permanece** no repositório por enquanto; remoção física só quando solicitada explicitamente.

## Decisão

### Continua importável (cadastro / parâmetros)

- Empresas, unidades de negócio, fornecedores, veículos, clientes, frutas, ICMS, estados, praças, grupos, etc.
- Qualquer planilha cujo objetivo seja **atualizar cadastro mestre** ou tabelas de apoio.

### Deixa de ser usado operacionalmente (movimentações)

- Importação de **vendas**, **transferências**, **estoques** (saldo/custo inicial em massa fora de migração pontual).
- Novas operações: pedidos → pipeline Lucas/Jefferson ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)).
- Rotas, telas e permissões de importação de movimentação **não são removidas** nesta fase; podem exibir aviso “legado — use captação” quando o pacote estiver ativo.

### Código legado

- **Manter** controllers, jobs e testes existentes até pedido de remoção.
- **Não** estender importações de movimentação com novas regras de negócio do pacote 0066+.
- Documentar em `SISTEMA_CONTEXTO.md` na virada de produção.

## Alternativas consideradas

- **Remover importação de movimentação já** — rejeitado; usuário pediu manter por enquanto.
- **Importar vendas em paralelo indefinidamente** — rejeitado; duplica origem da informação.

## Consequências

- [PLAN-0079](../plans/PLAN-0079-importacao-apenas-cadastro-sem-movimentacoes.md): banners UI, desabilitar menu opcional, sem delete de código.
- Treinamento: operação não usa mais planilha→venda/transferência após go-live do pacote.
