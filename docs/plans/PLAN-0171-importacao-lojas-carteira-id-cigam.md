# PLAN-0171: Importação de lojas na carteira por ID CIGAM

**ADR:** [ADR-0171](../decisions/ADR-0171-importacao-lojas-carteira-id-cigam.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Permitir vincular lojas à carteira via Excel (coluna A = id_cigam cliente) com preview na tela Editar carteira.

## Pré-requisitos

- ADR-0086 (carteiras e vínculo `clientes.id_captacao_carteira`).

## Passos

1. **Migration + model** — `captacao_carteira_importacoes`.
2. **Serviços** — normalizer, read filter, processor; `CaptacaoCarteiraService::adicionarLojas`.
3. **Job + controller + rotas** — fluxo iniciar/status/resultado/confirmar.
4. **UI** — botão em editar carteira; view importar; planilha modelo.
5. **Testes** — processor e confirmação.

## Critério de conclusão

- Upload gera preview (novas / já na carteira / erros).
- Confirmar vincula sem remover lojas existentes.
- Testes PHPUnit passando.

## Riscos

- Cliente em outra carteira — mitigação: erro explícito na linha.
