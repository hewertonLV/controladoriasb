# ADR-0007: Nome do sistema SB - CONTROLADORIA

**Data:** 2026-05-17
**Status:** Aceito
**Contexto:** Atualização de marca exibida nas telas e documentação

## Contexto

O sistema ainda exibia `facigam` como nome antigo em telas, configuração e documentação.
O nome atual informado para a aplicação é `SB - CONTROLADORIA`.

## Decisão

Usar `SB - CONTROLADORIA` como nome textual do sistema em tela, exemplos de ambiente e documentação geral.
Manter `CIGAM` quando for referência ao ERP, campos como `id_cigam` ou identificadores técnicos de ambiente.

## Alternativas consideradas

- Trocar todas as ocorrências de `facigam` indiscriminadamente — rejeitado por risco de quebrar containers, volumes, paths e nomes técnicos.
- Trocar apenas a tela encontrada — rejeitado porque `APP_NAME` e documentação continuariam exibindo a marca antiga.

## Consequências

As telas passam a exibir a marca atual via `APP_NAME` e textos Blade.
Referências técnicas legadas permanecem estáveis até uma eventual migração planejada de infraestrutura.
