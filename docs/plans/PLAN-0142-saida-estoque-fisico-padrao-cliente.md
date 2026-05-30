# PLAN-0142: Padrão de saída estoque físico no cadastro do cliente

**ADR:** [ADR-0142](../decisions/ADR-0142-saida-estoque-fisico-padrao-cliente.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Permitir definir no cliente se a saída física padrão na captação é galpão ou HUB e refletir isso na aba «Saída estoque físico».

## Pré-requisitos

- ADR-0129 e coluna `pedidos.id_unidade_negocio_saida_venda` já existentes.

## Passos

1. **Migration** — `clientes.saida_estoque_fisico_padrao` (default `galpao`).
2. **Enum + resolver** — `ClienteSaidaEstoqueFisicoPadrao`, `SaidaEstoqueFisicoCaptacaoService`.
3. **Cadastro cliente** — formulário, validação, model cast.
4. **Captação** — `garantirSaidaFisicaVendaPadraoGalpao`, blade, matriz estado, frete/vendas.
5. **Testes** — cliente CRUD + captação com padrão HUB.

## Critério de conclusão

- Cliente salva preferência; upload NF ou aba exibem HUB/galpão conforme cadastro e lote; testes verdes.

## Riscos

- Pedidos antigos com galpão já gravado não mudam sozinhos — operador ajusta na aba se necessário.
