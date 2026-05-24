---
name: brainstorm-adr-pacote-implementacao
description: Use ao desenhar ou evoluir um módulo/feature com várias ADRs encadeadas. Conduz brainstorm, cria ou atualiza ADRs e PLANs 1:1, e mantém um PACOTE em docs/pacotes/ como índice do conjunto. Use quando o usuário pedir brainstorm de ADR, pacote de implementação, ou ao registrar fluxos operacionais multi-etapa (ex. captação, romaneio, Cigan).
---

# Brainstorm ADR + pacote de implementação

## Quando usar

- Nova feature com **várias decisões** (regras de negócio + UI + API + integração).
- Refinamento de fluxo já documentado (atualizar ADRs existentes em vez de duplicar).
- Usuário pede **pacote** ou **brainstorm** antes de codar.

## Ordem obrigatória

1. **Brainstorm** — resumir fluxo, atores, portões (botões), travas; listar o que falta decidir.
2. **Mapear ADRs** — uma decisão por ADR; próximo número em `docs/decisions/`.
3. **Criar/atualizar ADRs** — skill `criar-decision-record` (sem passos de implementação na ADR).
4. **Criar/atualizar PLANs** — skill `criar-plano-implementacao` (1:1 com cada ADR).
5. **Pacote** — criar ou atualizar `docs/pacotes/PACOTE-NNNN-slug.md` (índice, ordem de execução, diagrama, links).
6. **Sincronizar referências** — ADRs do pacote linkam entre si; status do lote único na ADR de orquestração quando existir.

## Formato do PACOTE

Salve em `docs/pacotes/PACOTE-NNNN-slug-curto.md`. Use o **menor número** do bloco de ADRs (ex.: ADRs 0066–0070 → `PACOTE-0066-...`).

```markdown
# PACOTE-NNNN: Título do conjunto

**Data:** YYYY-MM-DD
**Status:** Em definição | Pronto para implementar | Em andamento | Concluído

## Objetivo

Uma frase do que o pacote entrega de ponta a ponta.

## ADRs e planos

| # | ADR | PLAN | Tema |
|---|-----|------|------|
| 0066 | [link] | [link] | ... |

## Fluxo resumido

(diagrama mermaid ou texto)

## Ordem de implementação

1. PLAN-XXXX — ...
2. ...

## Fora de escopo (neste pacote)

- ...

## Pendências / specs futuras

- Layout Cigan, etc.
```

## Regras

- **Não codar** nesta skill — só documentação.
- Atualizar pacote quando **qualquer** ADR do conjunto mudar.
- Conflito entre ADRs antiga e nova → **editar** a ADR existente + nota “atualizado” no cabeçalho; nova ADR só se for decisão distinta.
- Gate pré-implementação: após pacote “Pronto para implementar”, usar `pre-implementacao-gate` antes de `Shell`/código.

## Saída para o usuário

Informar: ADRs criadas/alteradas, PACOTE path, ordem sugerida de PLANs, pendências abertas.
