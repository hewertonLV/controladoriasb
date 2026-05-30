# ADR-0143: Saída estoque físico padrão por unidade de negócio no cliente

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Cadastro de cliente ([ADR-0142](ADR-0142-saida-estoque-fisico-padrao-cliente.md))

## Contexto

O enum `galpao` | `hub` não permitia escolher qual galpão ou HUB concreto usar. A operação precisa ver as mesmas unidades relevantes: galpão do faturamento da loja e galpões/HUB da rede.

## Decisão

- Substituir `saida_estoque_fisico_padrao` por `id_unidade_negocio_saida_fisico_padrao` (FK em `unidades_negocio`).
- Opções no formulário (conforme unidade de faturamento do cliente):
  1. **Galpão do faturamento** — galpões operacionais das carteiras de captação daquela UN de faturamento.
  2. **Galpões da rede (HUB → galpão)** — demais galpões operacionais de carteiras ativas.
  3. **HUB** — unidades com `is_hub` ativas (saída direta no HUB na captação).
- Na captação, usar o ID salvo se estiver entre as unidades permitidas do lote; senão, mapear por tipo (galpão → galpão do lote, HUB → HUB do lote).

## Alternativas consideradas

- Manter enum e só mudar labels — rejeitado; não identifica unidade concreta.
- Listar todas as UN do sistema — rejeitado; polui o select.

## Consequências

- [PLAN-0143](../plans/PLAN-0143-cliente-saida-fisico-unidade-negocio.md).
- Atualiza [ADR-0142](ADR-0142-saida-estoque-fisico-padrao-cliente.md) (campo concreto em vez de enum).
