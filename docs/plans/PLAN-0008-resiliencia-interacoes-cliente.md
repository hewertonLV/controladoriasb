# PLAN-0008: Resiliência das interações do cliente

**ADR:** [ADR-0008](../decisions/ADR-0008-resiliencia-interacoes-cliente.md)
**Data:** 2026-05-18
**Status:** Concluído

## Objetivo

Garantir que submits, navegação e loaders tenham recuperação e diagnóstico quando falharem no primeiro clique/carregamento.

## Pré-requisitos

- Identificar scripts globais carregados pelo layout.
- Confirmar que a proteção contra duplo submit deve ser preservada.

## Passos

1. **Criar regressão** — cobrir carregamento do script, recuperação do guard e endpoint de log.
2. **Recuperar submits** — restaurar botões travados em `pageshow` e evento global de recuperação.
3. **Registrar erros do cliente** — adicionar endpoint Laravel e script para `error`/`unhandledrejection`.
4. **Carregar script central** — incluir o script após o guard de submit nos layouts.
5. **Verificar** — executar testes focados e revisar lints dos arquivos alterados.

## Critério de conclusão

O teste de regressão passa e os scripts globais possuem recuperação de estado e registro de erro.
O guard continua bloqueando duplo submit enquanto o primeiro envio está em andamento.

## Riscos

- Log excessivo de erros repetidos — mitigar deduplicando no navegador e limitando payload no request.
