# ADR-0105: Arquivo Cigan EDI NF — transferência com HUB de origem

**Data:** 2026-06-04
**Status:** Aceito
**Contexto:** Download TXT na fase `TRANSFERENCIA_CIGAN_INICIADA` ([ADR-0103](ADR-0103-matriz-aba-arquivo-cigan-txt.md), layout `EDI_NF_CIGAM.pdf`)

## Contexto

O Cigan importa NF via TXT de largura fixa (registros `N` nota + `I` itens). A transferência fiscal HUB → galpão usa as quantidades **a receber** do Romaneio 2. A origem física (HUB) varia por operação e deve ser escolhida antes do arquivo.

## Decisão

- Persistir `captacao_lotes.id_unidade_negocio_hub_origem` (UN com `is_hub = true`).
- Na aba **Arquivo Cigan**, obrigar **salvar o HUB** antes de habilitar o download.
- Gerar TXT com `CiganEdiNfTransferenciaGerador`:
  - **Registro N:** NF de **entrada** (`E`, módulo Compras); cliente/cobrança = código Cigam da **unidade de faturamento da carteira** (`id_unidade_negocio_faturamento` do lote); transportadora = código Cigam do **HUB** de origem; data = `data_referencia` do lote; número = id do lote (10 dígitos). O **galpão** do lote define o romaneio (quantidades a receber), não o destino fiscal do TXT.
  - **Registro I:** um por fruta com `a_receber_um > 0`; código material = `frutas.id_cigam`; quantidade máscara N8.6; preço unitário mínimo (impostos calculados no Cigan).
- Somente campos **obrigatórios** do manual; opcionais em branco. Registros `O`, `L`, `G` etc. **não** são emitidos.
- Charset do arquivo: **ISO-8859-1**; comprimento linha N = 688, I = 719 (exemplo oficial).
- Defaults em `config/captacao_cigan_edi.php` (`tipo_operacao` 51101, série 1, condição 001, divisão 10).

## Alternativas consideradas

- Um HUB fixo no sistema — rejeitado; operação escolhe a unidade de saída.
- CSV legado do «Iniciar transferência» — mantido separado; TXT segue layout Cigam.
- Preencher todos os campos do PDF — rejeitado; escopo só obrigatórios + dados do cadastro SB.

## Consequências

- Cadastro: faturamento da carteira, HUB e frutas precisam de `id_cigam`; faturamento com CNPJ recomendado (IE enviada como `ISENTO` se não houver).
- SB **não** tem endereço/contato completo da UN — preenchimento com placeholders (`SB CONTROLADORIA`, `NAO INFORMADO`) nos campos obrigatórios de texto.
- Tipo de operação e materiais devem existir no Cigan; validação fiscal ocorre na importação.
- [PLAN-0105](../plans/PLAN-0105-arquivo-cigan-edi-transferencia-hub.md).
