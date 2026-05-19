# ADR-0013: ICMS por fruta, estado e operação (entrada/saída)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** ICMS estava em colunas fixas na tabela `frutas`, mas cada estado cobra em momento diferente (entrada ou saída) com valores distintos.

## Contexto

O `EstadoSeeder` documenta: Ceará paga ICMS na entrada; Pernambuco na venda; Alagoas não paga. Os valores (`icms_externo`, `icms_nacional`, `um_icms`, `icms_venda`) variam por produto e por estado.

## Decisão

Criar tabela `fruta_icms` com chave única `(fruta_id, id_estado, operacao)` onde `operacao` é `ENTRADA` ou `SAIDA`.

- **ENTRADA:** `icms_externo`, `icms_nacional`, `um_icms` (regra de compra/transferência para o estado).
- **SAIDA:** `icms_venda` (percentual para o estado).

Remover colunas de ICMS de `frutas`. Cálculo em movimentações consulta `fruta_icms` do estado da unidade destino, mantendo regras de aplicação já usadas (ex.: CE só cobra entrada quando fornecedor é de outro estado).

## Alternativas consideradas

- Manter colunas em `frutas` + JSON por estado — difícil de validar e indexar.
- Uma linha por estado com colunas entrada e saída — rejeitado; duas operações com semânticas diferentes ficam mais claras em linhas separadas.

## Consequências

- Cadastro e importação de frutas passam a preencher `fruta_icms` por estado.
- Histórico de fruta inclui snapshot de ICMS por estado.
- Importação Excel legada continua mapeando colunas H–E para ICMS do Ceará (estado id 1) até evolução da planilha.
