# ADR-0150: Módulo Centralizador — pipeline de lotes de captação

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Hub de módulos (ADR-0146) e topbar operacional de captação (ADR-0149).

## Contexto

O módulo **Captação** (ADR-0148) atende o vendedor em pedidos por loja. Operadores de backoffice precisam do pipeline administrativo de **lotes** (`/admin/captacao/lotes`) sem sidebar completa, com a mesma topbar operacional (Módulos, Criar Captação, título centralizado).

## Decisão

- Novo módulo **`Centralizador`** em `AppModulo`, entrada em `admin.captacao.lotes.index`.
- Acesso: permissão `captacao.lote.visualizar` e **sem** role `Vendedor` (vendedor permanece só no módulo Captação).
- Layout: oculta sidebar; reutiliza topbar de ADR-0149 (modal Criar Captação incluído).
- Na listagem de lotes com `app_modulo = centralizador`, o card inline «Abrir captação do dia» **não** é exibido (somente modal do topbar).
- Pós-criação de lote: redireciona para `lotes.show` (fluxo administrativo), distinto do módulo Captação.

## Alternativas consideradas

- Unificar Captação e Centralizador num único card — rejeitado: perfis e telas de entrada são distintos.
- Role dedicado Centralizador — rejeitado nesta fase: permissão existente + exclusão de Vendedor basta.

## Consequências

- Usuários com captação administrativa veem Captação e Centralizador no hub quando aplicável.
- Vendedor não vê Centralizador.
