# ADR-0016: Histórico operacional de ICMS e vigência na movimentação

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** ICMS de fruta muda no cadastro; movimentações e replay devem usar o valor vigente na data do evento.

## Contexto

`movimentacoes` já grava snapshot (`icms_convertido_kg`, `valor_icms_*`). Porém novos cálculos e recálculos sem snapshot precisam da tabela histórica operacional, não do cadastro atual nem de `fruta_historicos` (auditoria JSON).

## Decisão

1. Tabela `fruta_icms_historicos` com snapshot completo por `(fruta_id, id_estado)` e `created_at`, padrão `historico_c_o_un_ng` (`status_position`).
2. Registrar histórico em toda alteração via `FrutaIcmsSyncService` (formulário fruta, tela ICMS, importação).
3. `FrutaIcmsCalculoService` aceita `dataReferencia` e consulta histórico vigente na data; fallback para `fruta_icms` atual.
4. Compras/transferências passam `data_movimentacao` ao calcular ICMS na criação.
5. Tela de edição de ICMS exibe últimas versões do histórico.

## Alternativas consideradas

- Usar só `fruta_historicos` — rejeitado (ADR-0001: não operacional para cálculo).
- Não gravar histórico — rejeitado; impossível recalcular retroativo sem snapshot na movimentação.

## Consequências

- Backfill inicial a partir de `fruta_icms` vigente.
- Replay continua priorizando snapshot na própria movimentação.
