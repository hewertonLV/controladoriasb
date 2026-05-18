---
name: criar-decision-record
description: Use when any decision is made during a task — business rules, technical choices, data defaults, format selections, mapping rules, or any choice that could have been made differently. Must be invoked before responding whenever a decision is detected.
---

# Criar ADR (Architecture Decision Record)

## Overview

Toda tomada de decisão durante uma tarefa — seja de negócio, técnica ou de dados — deve ser registrada como ADR em `docs/decisions/` antes de concluir a resposta.

## Quando Criar um ADR

Crie sempre que houver uma **escolha** que poderia ter sido feita de outra forma:

- Regras de mapeamento (ex.: código legado → valor do sistema)
- Valores padrão adotados (ex.: status ATIVO quando não informado)
- Critérios de seleção (ex.: qual aba da planilha usar)
- Escolhas técnicas (ex.: qual algoritmo de correspondência aplicar)
- Limites e formatos (ex.: 6 dígitos para id_cigam)
- Exclusões explícitas (ex.: banana maçã não herda ICMS de maçã)
- Qualquer "poderia ter sido diferente?" com resposta "sim"

**Não crie ADR para:**
- Execução mecânica sem alternativa plausível (ex.: "salve o arquivo")
- Correção de erro óbvio onde só existe uma resposta correta

## Formato do ADR

Salve em `docs/decisions/ADR-NNNN-titulo-curto.md`.
Use o próximo número sequencial disponível (verifique os arquivos existentes).

```markdown
# ADR-NNNN: Título curto da decisão

**Data:** YYYY-MM-DD
**Status:** Aceito
**Contexto:** [tarefa ou módulo onde surgiu]

## Contexto

Situação que gerou a necessidade de decidir.

## Decisão

O que foi decidido e por quê esta opção foi escolhida.

## Alternativas consideradas

- Alternativa A — motivo de não ter sido escolhida
- Alternativa B — motivo de não ter sido escolhida

## Consequências

O que muda como resultado desta decisão. Riscos ou pontos de atenção em aberto.
```

## Regras

- **Sem plano de implementação** — o ADR documenta a decisão, não como executá-la
- **Objetivo e direto** — cada seção em no máximo 3-5 linhas
- **Um ADR por decisão** — se a tarefa gerar 3 decisões, crie 3 arquivos
- Informe o usuário ao final da resposta: `"Registrei em docs/decisions/ADR-NNNN-....md"`

## Fluxo

1. Ao receber a tarefa, identifique todas as decisões envolvidas
2. Verifique o último número em `docs/decisions/` para sequência correta
3. Crie o(s) ADR(s)
4. Execute a tarefa
5. Mencione os ADRs criados ao final
