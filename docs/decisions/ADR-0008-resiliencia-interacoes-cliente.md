# ADR-0008: Resiliência das interações do cliente

**Data:** 2026-05-18
**Status:** Aceito
**Contexto:** Cliques em submits, menus e carregamento inicial

## Contexto

Cliques em botões e links às vezes ficavam em estado de carregamento sem concluir.
Não havia recuperação central para submit travado nem log Laravel para erros JavaScript silenciosos.

## Decisão

Manter a proteção contra duplo submit, mas adicionar recuperação central de estado no retorno de página/cache.
Registrar erros JavaScript do navegador em endpoint Laravel dedicado para diagnóstico.

## Alternativas consideradas

- Remover o guard de duplo submit — rejeitado porque reintroduz duplicidade de ações críticas.
- Corrigir apenas cada formulário isolado — rejeitado por deixar menus, páginas e futuros formulários sem proteção comum.

## Consequências

Botões travados por navegação interrompida podem ser liberados sem exigir segundo clique.
Erros de console passam a gerar evidência no log Laravel, com payload limitado para evitar ruído excessivo.
