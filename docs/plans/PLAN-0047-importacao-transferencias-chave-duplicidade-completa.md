# PLAN-0047: Importação de transferências — duplicidade com NF e quantidade

**ADR:** [ADR-0047](../decisions/ADR-0047-importacao-transferencias-chave-duplicidade-completa.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir várias linhas com mesma origem, destino e fruta quando NF ou quantidade diferem.

## Passos

1. Ajustar `chaveUnicidadePlanilha` no processor.
2. Testes de preview com NFs distintas e linha repetida.

## Critério de conclusão

- Duas linhas iguais em tudo → erro de duplicidade.
- Mesma fruta/par com NF diferente → duas linhas em **Novas**.
