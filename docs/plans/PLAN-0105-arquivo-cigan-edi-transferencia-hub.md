# PLAN-0105: Arquivo Cigan EDI NF — transferência com HUB de origem

**ADR:** [ADR-0105](../decisions/ADR-0105-arquivo-cigan-edi-transferencia-hub.md)
**Data:** 2026-06-04
**Status:** Concluído

## Objetivo

Gerar TXT EDI NF Cigam na matriz (fase transferência iniciada) com quantidades a receber e HUB de origem obrigatório.

## Pré-requisitos

- ADR-0103 (aba Arquivo Cigan).
- Romaneio 2 (`RomaneioAbastecimentoService`).

## Passos

1. Migration `id_unidade_negocio_hub_origem` em `captacao_lotes`.
2. `CiganEdiLinha` + `CiganEdiNfTransferenciaGerador` + `config/captacao_cigan_edi.php`.
3. UI aba Arquivo Cigan: select HUB, prévia romaneio, download condicionado.
4. Rotas PUT hub + GET download; testes feature/unit.

## Critério de conclusão

- Download só com HUB salvo; linhas N (688) e I (719) com itens a receber; testes passando.

## Riscos

- Campos obrigatórios sem dado no SB — mitigação: placeholders + documentação na ADR.
- Rejeição no Cigan por tipo de operação/material — mitigação: config/env e cadastro `id_cigam`.
