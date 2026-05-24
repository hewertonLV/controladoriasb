# ADR-0083: Romaneio manual estilo matriz e botões sequenciais do pipeline

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** UX de captação e romaneio manual ([ADR-0074](ADR-0074-romaneio-manual-abastecimento-sem-captacao.md), [ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md))

## Contexto

O romaneio manual era criado em formulário único com várias linhas. A matriz de captação já salva quantidades automaticamente e permite incremento por teclado. O lote e o romaneio exibiam todos os botões do pipeline ao mesmo tempo, permitindo tentativa de pular etapas na interface.

## Decisão

### Romaneio manual

- Abertura do lote só com data, faturamento e galpão; edição em tela dedicada.
- Inclusão de **uma fruta por vez** (fruta + origem HUB); quantidade em **caixas** (inteiro, `step=1`).
- Salvamento **automático** via API (`PATCH` linha, `incremento` com ↑↓), como a matriz.
- Pipeline na própria tela: **Fechar romaneio** → **Iniciar transferência** → **Concluir transferência** (valida gerencial e encerra em `TRANSFERENCIA_FINALIZADA`, sem etapa Jefferson nem frete obrigatório).

### Botões sequenciais

- Componente único (`CaptacaoLotePipelineUi`) define a **próxima** ação permitida pelo `status` e `tipo` do lote.
- Telas de lote de captação e romaneio manual exibem **somente** esse botão; regras de status nas Actions impedem avanço fora de ordem.

## Alternativas consideradas

- Manter formulário em lote na criação — rejeitado; não replica UX da matriz.
- Exibir todos os botões desabilitados — rejeitado; poluição visual e risco de confusão.
- Romaneio manual com etapa de frete separada — rejeitado para este fluxo; frete opcional permanece só em captação com pedidos.

## Consequências

- [PLAN-0083](../plans/PLAN-0083-romaneio-manual-matriz-e-pipeline-sequencial.md).
- Romaneio manual: rotas `editar`, `frutas.store`, `linhas.update`, `concluir-transferencia`.
