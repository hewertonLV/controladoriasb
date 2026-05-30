# ADR-0146: Hub de módulos e contexto operacional do vendedor

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Visualização administrativa completa não serve ao vendedor; é necessária tela inicial com módulos.

## Contexto

O layout admin (`layouts.app` + sidebar) expõe todo o sistema. Vendedores operam apenas Captação, Transferência e Venda, sem menu lateral completo.

## Decisão

- Tela inicial autenticada: **hub** em `/modulos`, listando cards dos módulos permitidos ao usuário.
- Ao entrar em um módulo, gravar `app_modulo` na sessão e redirecionar para a URL de entrada do módulo.
- **Administrador**: roles `Administrador`, `Programador` ou `Controladoria`; entrada no dashboard com sidebar completa.
- **Captação**: qualquer permissão `captacao.*`; entrada em listagem de lotes.
- **Transferência**: `movimentacoes.transferencias.visualizar`; entrada na listagem de transferências.
- **Venda**: `movimentacoes.vendas.visualizar`; entrada na listagem de vendas.
- Com `app_modulo` operacional (captação/transferência/venda), o layout admin **oculta** a sidebar; topbar volta ao hub.
- Login e `/` redirecionam para o hub, não mais direto ao dashboard.

## Alternativas consideradas

- Rotas duplicadas `/vendedor/*` — rejeitado nesta fase: duplicaria controllers; sessão de contexto reutiliza telas existentes.
- Novo role fixo “Vendedor” — implementado em ADR-0147.

## Consequências

- Telas admin dentro de módulo operacional ficam sem menu lateral até voltar ao hub ou abrir Administrador.
- Dashboard financeiro permanece exclusivo do módulo Administrador.
- Evolução futura: menus e home específicos por módulo em `resources/views/modulos/{modulo}/`.
