# PLAN-0085: Importação de vínculos fruta×loja por planilha

**ADR:** [ADR-0085](../decisions/ADR-0085-importacao-vinculo-fruta-loja-planilha.md)
**Data:** 2026-05-25
**Status:** Concluído

## Objetivo

Permitir importar vínculos loja×fruta via Excel (A=loja, B=fruta) com preview e confirmação na tela Frutas por loja.

## Pré-requisitos

- ADR-0071 (tabela `cliente_fruta_vinculos`)
- Permissão `captacao.cliente_fruta.vincular`

## Passos

1. **Migration + model** — `cliente_fruta_importacoes` com `id_unidade_negocio_faturamento`.
2. **Serviços** — ReadFilter A–B, normalizer, processor (preview).
3. **Job + controller + rotas** — fluxo iniciar/status/resultado/confirmar.
4. **UI** — botão na listagem, view importar, planilha modelo referenciada.
5. **Testes** — feature test do processor e confirmação.

## Critério de conclusão

- Upload gera preview com novos vínculos, já vinculados e erros.
- Confirmar cria vínculos sem remover existentes.
- Testes PHPUnit passando.

## Riscos

- Nomes duplicados entre lojas — mitigação: erro de ambiguidade na linha.
- Frutas com nomes parecidos — mitigação: match exato após normalização.
