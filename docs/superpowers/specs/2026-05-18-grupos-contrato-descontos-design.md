# Grupos de Contrato e Descontos Mensais

**Data:** 2026-05-18
**Status:** Aprovado para planejamento

## Objetivo

Substituir o desconto de contrato gravado diretamente em `clientes.desconto_contrato` por uma estrutura própria de Grupo de Contrato, com lançamentos mensais de desconto em R$ e histórico de quais clientes pertenciam ao grupo em cada competência.

## Contexto Atual

Hoje o cliente possui `desconto_contrato` como coluna própria. Esse campo aparece em cadastro, validação, importação, exportação PDF, auditoria e testes.

Também existe `grupo_id` em `clientes`, apontando para `grupos`. Esse cadastro atual deve continuar separado da nova estrutura, porque o novo conceito é específico de contrato e desconto mensal.

## Decisões de Produto

- Criar uma estrutura nova chamada `grupo_contrato`, separada de `grupos`.
- Remover `desconto_contrato` da tabela `clientes`.
- Lançar desconto por Grupo de Contrato e por competência mensal.
- O desconto será valor em R$, não percentual.
- Um mês pode não ter lançamento de desconto.
- O lançamento será inicialmente informativo/controle, sem rateio automático por cliente.
- A participação de clientes no grupo será controlada por competência mensal.
- O sistema deve responder diretamente quais clientes faziam parte de um Grupo de Contrato em determinada competência.

## Modelo de Dados Proposto

### `grupos_contrato`

Cadastro mestre do grupo contratual.

Campos principais:

- `id`
- `nome`
- `descricao` nullable
- `ativo` boolean
- `created_by` nullable
- `updated_by` nullable
- `deleted_at`
- timestamps

### `grupo_contrato_clientes`

Linha do tempo de participação mensal dos clientes.

Campos principais:

- `id`
- `grupo_contrato_id`
- `cliente_id`
- `competencia_inicio` no formato `YYYY-MM`
- `competencia_fim` nullable no formato `YYYY-MM`
- `created_by` nullable
- `updated_by` nullable
- `deleted_at`
- timestamps

Regras:

- Um cliente não pode ter duas participações ativas/conflitantes no mesmo Grupo de Contrato para a mesma competência.
- `competencia_fim = null` significa participação vigente sem previsão de saída.
- Para consultar uma competência, o cliente pertence ao grupo quando `competencia_inicio <= competencia` e (`competencia_fim` é null ou `competencia_fim >= competencia`).

### `grupo_contrato_descontos`

Lançamentos mensais de desconto do grupo.

Campos principais:

- `id`
- `grupo_contrato_id`
- `competencia` no formato `YYYY-MM`
- `valor` decimal 15,2
- `valor_teto` decimal 15,2 nullable
- `observacao` nullable
- `created_by` nullable
- `updated_by` nullable
- `deleted_at`
- timestamps

Regras:

- No máximo um lançamento por Grupo de Contrato e competência.
- `valor` deve ser maior ou igual a zero.
- `valor_teto`, quando informado, deve ser maior ou igual a zero.
- Meses sem lançamento devem ser tratados como “sem desconto lançado”, não como erro.

## Histórico e Auditoria

Criar histórico próprio para Grupo de Contrato, participação de clientes e descontos mensais, seguindo o padrão dos históricos existentes:

- `grupo_contrato_historicos`
- `grupo_contrato_cliente_historicos`
- `grupo_contrato_desconto_historicos`

Cada histórico deve guardar:

- usuário responsável
- origem (`MANUAL` inicialmente)
- ação (`CRIACAO`, `ATUALIZACAO`, `REMOCAO` ou equivalente)
- dados antes
- dados depois
- alterações calculadas
- data/hora

## Impacto em Clientes

Remover `desconto_contrato` de:

- migration/tabela `clientes`
- model `Cliente`
- factory `ClienteFactory`
- validação `ValidatesClienteAttributes`
- formulário de cliente
- importação de clientes
- exportação PDF de clientes
- auditoria de clientes
- query/sorts de clientes
- testes de clientes

O cadastro de cliente poderá continuar mostrando o grupo atual antigo (`grupo_id`) enquanto o novo Grupo de Contrato for implementado em telas próprias. A migração do uso operacional para Grupo de Contrato deve ser feita sem misturar os dois conceitos.

## Telas Propostas

### Listagem de Grupos de Contrato

Exibir nome, status, quantidade de clientes vigentes na competência atual e último desconto lançado.

Ações:

- criar
- editar
- ver membros
- lançar desconto mensal
- ver histórico

### Membros do Grupo

Permitir adicionar cliente com `competencia_inicio` e, opcionalmente, `competencia_fim`.

Permitir encerrar participação informando `competencia_fim`.

### Descontos Mensais

Permitir lançar ou editar valor em R$ por competência.

Exibir linha do tempo dos lançamentos, com meses sem lançamento visíveis quando houver filtro por período.

## Regras de Consulta

Para consultar membros por competência:

```text
competencia_inicio <= competencia
AND (competencia_fim IS NULL OR competencia_fim >= competencia)
```

Para consultar desconto por competência:

```text
grupo_contrato_id = X
AND competencia = YYYY-MM
```

Se não existir lançamento, exibir “sem lançamento”.

## Fora do Escopo Inicial

- Rateio automático do desconto entre clientes.
- Cálculo automático a partir de perdas.
- Integração do desconto com movimentações ou financeiro.
- Substituir o cadastro atual de `grupos`.

## Critérios de Aceite

- `clientes.desconto_contrato` deixa de existir e não é mais usado no cadastro de clientes.
- É possível criar Grupo de Contrato.
- É possível vincular cliente ao Grupo de Contrato por competência mensal.
- É possível consultar quem fazia parte do grupo em uma competência.
- É possível lançar valor de desconto mensal em R$ para o grupo.
- Meses sem lançamento são aceitos e exibidos claramente como sem lançamento.
- Alterações de grupo, membros e descontos ficam auditáveis.
- Testes cobrem remoção do campo antigo, vínculos temporais e lançamento mensal.
