# ADR-0149: Topbar do módulo Captação sem sidebar

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Hub de módulos (ADR-0146) e entrada em pedidos por loja (ADR-0148).

## Contexto

Com `app_modulo = captacao`, a sidebar administrativa fica oculta, mas o topbar do tema ainda reservava margem da sidenav. O vendedor precisa de ações rápidas (voltar ao hub e abrir nova captação) sem depender do formulário embutido na página de carteiras.

## Decisão

- No módulo Captação, o topbar ocupa **100% da largura** (mesma regra de margem zero que o conteúdo operacional).
- Layout do topbar em três zonas: **esquerda** — botões «Módulos» e «Criar Captação»; **centro** — `@section('page-title')`; **direita** — tema e usuário.
- «Criar Captação» abre modal Bootstrap (`_modal-criar-captacao`) com campos **data** e **carteira** (POST `admin.captacao.lotes.store`).
- Na tela de carteiras, com módulo Captação ativo, o formulário inline «Abrir captação do dia» **não** é exibido (evita duplicidade com o modal do topbar).
- Demais módulos operacionais (transferência/venda) mantêm o topbar genérico, apenas com margem zerada.

## Alternativas consideradas

- Link para `#abrir-captacao` na página de carteiras — rejeitado: em lojas/pedido o vendedor precisaria navegar antes de criar.
- Manter formulário inline e no topbar — rejeitado: duplicidade visual.

## Consequências

- Carteiras no contexto do módulo depende do modal do topbar para abrir captação.
- View composer injeta lista de carteiras ativas no topbar quando `app_modulo = captacao`.
