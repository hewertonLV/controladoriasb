# ADR-0126: Arquivo Cigan EDI NF — vendas (faturamento → loja)

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Início do faturamento Cigam ([ADR-0104](ADR-0104-preco-editavel-ate-faturamento-cigan.md)); layout igual ao de transferência ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

Ao **iniciar o faturamento**, Jefferson precisa do TXT para importar as NFs de **venda** no Cigam. A origem comercial é a **unidade de faturamento**; o destino é cada **loja** (cliente). O layout de posições é o mesmo do arquivo de transferência, mas as regras de preenchimento divergem e evoluirão só neste fluxo.

## Decisão

- Gerador dedicado `CiganEdiNfVendaGerador` (não alterar `CiganEdiNfTransferenciaGerador`).
- Configuração em `config/captacao_cigan_edi_vendas.php` (defaults espelhados da transferência; tipo de operação e demais particularidades de venda mudam só aqui).
- Um registro **N + itens I** por **loja** com itens de pedido (`quantidade > 0`) no lote.
- **Cliente/cobrança** (52–57, 59–64), nome, CNPJ e UF: **loja** (destino).
- **Unidade de negócio** (602–604 / 656–658): **faturamento** (`id_cigam`, origem).
- **Centro armazenagem** (605–607): `centro_armazenagem` da unidade de **faturamento**.
- **Série/número NF** (3–15): `NF` + `id_cigam` da **unidade de faturamento** (origem), mesma regra de [ADR-0115](ADR-0115-cigan-edi-numero-nf-unidade-negocio.md) — não usar o `id_cigam` da loja.
- Material, quantidade N8.6, espécie `S`: iguais à transferência.
- **Preço unitário** (pos. 56–70, N10.5): `preco_venda` da matriz por UM — [ADR-0127](ADR-0127-cigan-edi-vendas-preco-unitario-matriz.md) (transferência permanece em branco).
- **Tipo de operação** (pos. 20–24 no `N` e 372–376 no `I`): **em branco** nas vendas (não usar `5152A` da transferência).
- Download na aba **Arquivo Cigam** quando status `FATURAMENTO_CIGAN_INICIADO` ou `VENDAS_FINALIZADAS`.
- Permissão: `captacao.lote.faturamento.iniciar` (mesmo ator do faturamento).

## Alternativas consideradas

- Reutilizar o gerador de transferência com flag — rejeitado; risco de alterar transferência ao evoluir vendas.
- Um único N para o lote inteiro — rejeitado; Cigam exige NF por destino (loja).

## Consequências

- [PLAN-0126](../plans/PLAN-0126-arquivo-cigan-edi-vendas-faturamento.md).
- Cadastro: faturamento e lojas com `id_cigam`; frutas com `id_cigam`; UF da loja; centro de armazenagem no faturamento.
