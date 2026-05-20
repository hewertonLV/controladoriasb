# PLAN-0007: Nome do sistema SB - CONTROLADORIA

**ADR:** [ADR-0007](../decisions/ADR-0007-nome-sistema-sb-controladoria.md)
**Data:** 2026-05-17
**Status:** Concluído

## Objetivo

Atualizar textos de marca do sistema para `SB - CONTROLADORIA`.

## Pré-requisitos

- Nome atual do sistema confirmado como `SB - CONTROLADORIA`.
- Escopo confirmado para telas, exemplos de ambiente e documentação geral.

## Passos

1. **Atualizar configuração** — alterar `APP_NAME` local e exemplos para `SB - CONTROLADORIA`.
2. **Atualizar telas** — substituir menções visíveis ao nome antigo nas views.
3. **Atualizar documentação** — trocar menções de marca em README e documento mestre, preservando identificadores técnicos.
4. **Verificar ocorrências** — buscar remanescentes de `controladoriasb` e revisar se são técnicas ou pendentes.

## Critério de conclusão

As telas e documentos gerais não exibem mais `controladoriasb` como nome do sistema.
Ocorrências remanescentes devem estar restritas a referências técnicas ou legadas.

## Riscos

- Substituir nomes técnicos pode quebrar ambiente Docker ou deploy — mitigar revisando cada ocorrência antes de alterar.
