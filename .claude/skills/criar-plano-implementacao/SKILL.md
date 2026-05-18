---
name: criar-plano-implementacao
description: Use immediately after creating or identifying an ADR that does not yet have a corresponding implementation plan. Every ADR must have one plan in docs/plans/. Triggered automatically after criar-decision-record or when an ADR without a plan is detected.
---

# Criar Plano de Implementação

## Overview

Cada ADR representa uma decisão. Cada decisão precisa de um plano que descreva **como** executá-la. A relação é 1-para-1: um ADR → um plano.

## Quando Criar

- Imediatamente após criar um ADR (skill `criar-decision-record`)
- Quando detectar um ADR em `docs/decisions/` sem plano correspondente em `docs/plans/`
- Quando o usuário pedir explicitamente um plano para uma ADR existente

## Correspondência ADR ↔ Plano

O plano usa o mesmo número da ADR:

| ADR | Plano |
|-----|-------|
| `docs/decisions/ADR-0001-titulo.md` | `docs/plans/PLAN-0001-titulo.md` |
| `docs/decisions/ADR-0003-outro.md` | `docs/plans/PLAN-0003-outro.md` |

O título (slug) deve ser idêntico ao da ADR correspondente.

## Formato do Plano

Salve em `docs/plans/PLAN-NNNN-titulo-curto.md`.

```markdown
# PLAN-NNNN: Título curto (mesmo da ADR)

**ADR:** [ADR-NNNN](../decisions/ADR-NNNN-titulo-curto.md)
**Data:** YYYY-MM-DD
**Status:** Pendente | Em andamento | Concluído

## Objetivo

Uma frase: o que este plano entrega ao ser executado.

## Pré-requisitos

- O que deve estar pronto antes de iniciar (outros planos, dados, acesso)

## Passos

1. **Passo** — descrição objetiva do que fazer
2. **Passo** — ...
3. ...

## Critério de conclusão

Condições que confirmam que o plano foi executado com sucesso.

## Riscos

- Risco — mitigação
```

## Regras

- **Sem justificativas de decisão** — o ADR já explica o porquê; o plano só diz o como
- **Passos acionáveis** — cada passo deve ser executável por quem ler, sem ambiguidade
- **Status atualizado** — mude para `Em andamento` ao iniciar e `Concluído` ao terminar
- **Referência obrigatória à ADR** — todo plano deve linkar a ADR de origem no cabeçalho

## Fluxo

1. Identifique a ADR que origina o plano (número + título)
2. Verifique se já existe `docs/plans/PLAN-NNNN-*.md` com o mesmo número
3. Se não existir, crie o plano
4. Informe o usuário: `"Plano criado em docs/plans/PLAN-NNNN-....md"`

## Verificação de cobertura

Para checar ADRs sem plano correspondente:
```bash
for adr in docs/decisions/ADR-*.md; do
  num=$(basename "$adr" | grep -oP '\d{4}')
  plan=$(ls docs/plans/PLAN-${num}-*.md 2>/dev/null)
  [ -z "$plan" ] && echo "SEM PLANO: $adr"
done
```
