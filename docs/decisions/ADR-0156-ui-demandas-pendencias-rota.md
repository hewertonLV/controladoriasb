# ADR-0156: UI demandas e pendências na conclusão de rota

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Matriz de captação — aba Por rota (ADR-0154, ADR-0155)

## Contexto

Operadores precisam ver demandas de transferência/venda após concluir a rota e receber feedback legível quando a conclusão ainda não é permitida.

## Decisão

- **Pendências de conclusão:** exibir em **toast temporário** no canto superior direito (padrão `AdminDataTable.showToast`), com lista legível, ao clicar com critérios pendentes ou quando o servidor retorna 422 — sem modal bloqueante.
- **Validação de sequência:** ordens de carregamento devem ser contínuas de 1 a N (sem lacunas) por rota.
- **Erros técnicos (SQL/500):** nunca exibir mensagem bruta ao usuário na conclusão de rota; toast com orientação operacional.
- **Demandas:** cards no estilo da captação por loja (`captacao-loja-card`), na **tela inicial** dos módulos Movimentação — Transferências e Vendas (não na aba Por rota da matriz). Cada card abre a página de detalhe da demanda no respectivo módulo.
- **Transferência com origem física ≠ galpão operacional:** card exibe aviso fixo: *Demanda criada automaticamente para realizar no CIGAM, para efetivar a venda. Essa fruta não será transferida no SB Controladoria; servirá somente para FATURAMENTO FISCAL.*

## Alternativas consideradas

- **Modal AdminConfirm** — rejeitado para pendências; interrompe fluxo rápido na matriz.
- **Botão desabilitado sem clique** — rejeitado; tooltip estático com texto concatenado é difícil de ler.

## Consequências

- [PLAN-0156](../plans/PLAN-0156-ui-demandas-pendencias-rota.md).
- Complementa [ADR-0154](ADR-0154-transferencia-venda-pendente-conclusao-rota.md) e [ADR-0155](ADR-0155-status-demanda-rota-reabrir.md).
