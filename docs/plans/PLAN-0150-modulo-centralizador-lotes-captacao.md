# PLAN-0150: Módulo Centralizador — pipeline de lotes de captação

**ADR:** [ADR-0150](../decisions/ADR-0150-modulo-centralizador-lotes-captacao.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Card Centralizador no hub, entrada em lotes de captação, topbar igual ao módulo Captação e card inline oculto.

## Pré-requisitos

- ADR-0146, ADR-0149 implementados.
- Rota `admin.captacao.lotes.index` existente.

## Passos

1. **Enum** — `AppModulo::Centralizador` (label, ícone, cor, helpers de layout).
2. **ModuloHubService** — card, URL de entrada, regra de acesso.
3. **Layout** — topbar e modal para Captação + Centralizador; composer de carteiras.
4. **lotes/index** — ocultar card inline no módulo Centralizador.
5. **Testes** — hub, entrada, topbar e ausência para vendedor.

## Critério de conclusão

- Entrar em Centralizador grava sessão e abre `/admin/captacao/lotes` com topbar operacional.
- Testes PHPUnit relacionados passam.

## Riscos

- Usuário com permissão avulsa ver Captação e Centralizador — mitigação: documentado na ADR.
