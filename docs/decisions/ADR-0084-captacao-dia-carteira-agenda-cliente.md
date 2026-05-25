# ADR-0084: Captação por dia × carteira e agenda do cliente

**Data:** 2026-05-30
**Status:** Aceito
**Contexto:** Evolução do módulo Captação (PACOTE-0066); operação por carteira comercial

## Contexto

A captação deixa de ser organizada apenas por par faturamento + galpão na UI e passa a ser **dia × carteira**. A carteira agrupa a unidade de **faturamento** (NF) e o **galpão/estoque físico**. Clientes pertencem a uma carteira. Cada cliente pode ter **vários dias da semana** em que o pedido é **criado** e **vários dias** em que o pedido é **enviado** (logística distinta da data de captação D0).

## Decisão

### Carteira (`captacao_carteiras`)

- Campos: `nome`, `id_unidade_negocio_faturamento`, `id_unidade_negocio_galpao`, `ativo`.
- Várias carteiras podem compartilhar o mesmo `(faturamento, galpão)` — ver [ADR-0086](ADR-0086-multiplas-carteiras-mesmo-faturamento-galpao.md).
- Um lote de captação de pedidos referencia **uma carteira** (`captacao_lotes.id_captacao_carteira`); faturamento e galpão do lote são derivados da carteira (colunas legadas mantidas sincronizadas).
- Unicidade do lote: `(data_referencia, id_captacao_carteira)` para tipo `CAPTACAO_PEDIDOS`.

### Cliente

- `clientes.id_captacao_carteira` (nullable na migração; obrigatório na operação via validação ao vincular captação).
- Agenda em `cliente_captacao_agenda`: `(id_cliente, dia_semana, tipo)` com `tipo` ∈ `CRIACAO_PEDIDO` | `ENVIO_PEDIDO`; várias linhas por cliente; `dia_semana` 0–6 (domingo = 0, padrão Carbon).

### Matriz / opções de loja

- Na captação, o seletor de lojas lista **todos os clientes ativos da carteira do lote**, não só os que já têm frutas vinculadas.
- Inclusão na matriz continua exigindo **ao menos uma fruta vinculada** ([ADR-0071](ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md)).

### Consulta “sem pedido criado”

- Tela somente leitura: clientes da carteira **sem pedido** no lote em captação da data (ou todos da carteira se o lote ainda não existir).
- Distinta do alerta habitual [ADR-0078](ADR-0078-alertas-lojas-sem-pedido-dia-semana.md) (padrão histórico 2/4 semanas).

### Inativação de carteira

- Inativar só pela listagem (não pelo formulário de edição).
- **Bloqueio:** carteira com ao menos uma loja (`clientes.id_captacao_carteira`) não pode ser inativada.
- Reativar é sempre permitido. Listagem em abas **Ativas** e **Inativas**.
- Abertura de captação e vínculo em cliente usam apenas carteiras **ativas**.

### Menu

- Item lateral **“Captação”** (antes “Lotes e matriz”) aponta para a listagem de captação do dia.

## Alternativas consideradas

- **Manter só faturamento + galpão na UI** — rejeitado; não reflete vocabulário operacional “carteira”.
- **Agenda só em JSON no cliente** — rejeitado; dificulta consulta e relatórios por dia da semana.
- **Exigir fruta vinculada para listar na matriz** — rejeitado para listagem; mantido só na inclusão.

## Consequências

- [PLAN-0084](../plans/PLAN-0084-captacao-dia-carteira-agenda-cliente.md).
- Cadastro admin de carteiras; ajuste formulário de cliente; migração com backfill de carteiras a partir de pares UN existentes nos lotes.
