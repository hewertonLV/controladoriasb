# PLAN-0052: Importação de transferências — alterar destino na prévia

**ADR:** [ADR-0052](../decisions/ADR-0052-importacao-transferencias-override-destino-preview.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir trocar a unidade de destino de cada linha pronta no preview antes de confirmar a importação.

## Pré-requisitos

- Fluxo de importação ADR-0040 operacional
- Permissão `movimentacoes.transferencias.importar-confirmar`

## Passos

1. **API resultado** — retornar `empresas_destino` (id, label, cnpj) filtradas por acesso do usuário.
2. **API confirmar** — validar e aplicar `id_empresa_destino_por_row` opcional por `row_id`.
3. **UI** — select de destino agrupado por origem + NF (rowspan); enviar mapa por linha na confirmação.
4. **Teste** — confirmar com override grava movimentação no destino escolhido.

## Critério de conclusão

- Preview exibe select de destino; confirmação grava com `id_empresa_destino` alterado quando informado.
- Teste feature passa.

## Riscos

- Destino inválido na confirmação — mitigado por validação do `TransferenciaMovimentacaoService` e checagem origem ≠ destino no controller.
