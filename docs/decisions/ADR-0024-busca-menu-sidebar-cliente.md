# ADR-0024: Busca de itens do menu no cliente

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Campo “Filtrar menu” no topo do layout não retornava resultados.

## Contexto

O modal de busca existia no topbar, mas sem lista de resultados nem script. O tema prevê `.search-result-box`, porém a integração Laravel estava incompleta.

## Decisão

Indexar no navegador os links visíveis de `.side-nav` (respeitando permissões já renderizadas) e filtrar em tempo real no modal, com navegação por clique ou Enter.

## Alternativas consideradas

- Endpoint Laravel com JSON do menu — rejeitado: duplicaria permissões e exigiria manutenção paralela ao Blade.
- Filtrar/ocultar itens direto na sidebar — rejeitado: UX do tema usa modal centralizado no topbar.

## Consequências

- Busca reflete apenas itens que o usuário já vê no menu.
- Novas rotas no sidebar passam a ser encontráveis automaticamente, sem cadastro extra.
