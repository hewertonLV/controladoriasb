# PLAN-0040: Importação de transferências por planilha (CNPJ, NF, id CIGAM)

**ADR:** [ADR-0040](../decisions/ADR-0040-importacao-transferencias-planilha-cnpj.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir importar transferências em lote via Excel, com preview assíncrono e confirmação seletiva das linhas válidas.

## Pré-requisitos

- Permissões `movimentacoes.transferencias.importar` e `importar-confirmar` no seeder.
- Unidades com CNPJ e `possui_estoque`; frutas com `id_cigam` e kg/UM > 0.
- Worker com fila `transferencias-importacao`.

## Passos

1. **Migration e model** — tabela `transferencia_importacoes` + `TransferenciaImportacao`.
2. **Processor e job** — leitura A–E, validação, resultado `novas`/`erros`.
3. **Controller e rotas** — iniciar, status, resultado, confirmar.
4. **UI** — botão na index + view `importar.blade.php`.
5. **Permissões e fila** — `Permissions.php`, `docker-compose.yml`, planilha modelo.
6. **Testes** — feature do processor e fluxo HTTP mínimo.

## Critério de conclusão

- Botão “Importar transferências” visível com permissão.
- Planilha válida gera preview; confirmar cria movimentações de transferência.
- Testes automatizados passam.

## Riscos

- CNPJ duplicado entre cadastros — mitigar mensagem “múltiplas unidades” se ocorrer.
- Saldo negativo na origem — permitido (ADR-0002); usuário deve conferir no preview.
