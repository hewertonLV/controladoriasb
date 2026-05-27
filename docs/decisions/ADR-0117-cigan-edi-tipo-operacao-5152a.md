# ADR-0117: Tipo de operação 5152A no EDI Cigan (transferência HUB)

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Importação EDI NF Cigan para transferência HUB → filial ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md), rotina EDI NF CIGAM NFE)

## Contexto

O gerador deixava o tipo de operação (pos. 20–24 no `N` e 372–376 no `I`) em branco, confiando só na regra fiscal do material. A NF de referência da operação usa CFOP **5152** (saída transferência). A documentação do importador permite informar o tipo de operação no TXT para aplicar incidências/estoque do cadastro desse tipo.

## Decisão

- Preencher **5152A** nas pos. **20–24** (registro `N`) e **372–376** (registro `I`).
- Valor configurável em `config/captacao_cigan_edi.php` (`tipo_operacao`, env `CAPTACAO_CIGAN_TIPO_OPERACAO`), default **`5152A`** (5 caracteres).

## Alternativas consideradas

- Manter em branco — rejeitado: operação pediu tipo explícito alinhado à transferência 5152.
- Só no registro `I` — rejeitado: manual trata os dois registros.

## Consequências

- [PLAN-0117](../plans/PLAN-0117-cigan-edi-tipo-operacao-5152a.md).
- Na importação Cigan, se marcar «Desconsiderar Regra Fiscal para Tipo de Operação do TXT», passa a valer **5152A** do arquivo.
- Atualizar texto da aba Arquivo Cigan na matriz.
