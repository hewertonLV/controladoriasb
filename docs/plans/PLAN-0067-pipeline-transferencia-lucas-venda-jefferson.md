# PLAN-0067: Pipeline Cigan e gerencial — Lucas e Jefferson

**ADR:** [ADR-0067](../decisions/ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Implementar etapas de Lucas (iniciar transferência + arquivo Cigan, validar transferências gerenciais, finalizar) e Jefferson (iniciar faturamento + arquivo Cigan, finalizar vendas no SB), com travas de romaneio e romaneio complementar.

## Pré-requisitos

- [PLAN-0070](PLAN-0070-finalizar-captacao-unidade-faturamento.md) concluído (Atanásio finalizou faturamento).
- Especificação futura dos layouts Cigan (MVP: export placeholder versionado).

## Passos

1. **Status do lote** — enum/constantes conforme ADR-0067; transições validadas no domínio.
2. **Travas de edição** — middleware/policy em API e painel: bloquear qtd após iniciar transferência; bloquear preço após finalizar transferência.
3. **Iniciar transferência (Lucas)** — botão; snapshot quantidades; `GerarArquivoCiganTransferenciaLote`; download; status `TRANSFERENCIA_CIGAN_INICIADA`.
4. **Validar transferências (Lucas)** — `EfetivarTransferenciasGerenciaisLote`; HUB→galpão; status `AGUARDANDO_VINCULO_FRETE`.
5. **Vincular frete do lote** — ver [PLAN-0072](PLAN-0072-vinculo-frete-pos-transferencia-lote.md).
6. **Concluir etapa de frete (Lucas)** — frete opcional por linha; status `TRANSFERENCIA_FINALIZADA`; romaneio travado.
7. **Romaneio complementar** — fluxo enxuto repetindo passos 3–6 só para aditivos.
8. **Iniciar faturamento (Jefferson)** — validar estoque galpão; `GerarArquivoCiganVendasLote`; download; status `FATURAMENTO_CIGAN_INICIADO`.
9. **Finalizar venda (Jefferson)** — `FinalizarVendasLote`; movimentações de venda; status `VENDAS_FINALIZADAS`.
10. **Telas admin** — fila de lotes por status; ações por papel; histórico de downloads Cigan.
11. **Permissões e testes** — fluxo completo + complementar + frete opcional + bloqueios de edição.

## Critério de conclusão

- Lucas: iniciar → Cigan → validar → tela frete (opcional por linha) → concluir etapa frete → romaneio travado.
- Jefferson: iniciar faturamento → baixa Cigan vendas → finalizar → vendas no SB.
- Quantidades não editáveis após iniciar transferência; preços até concluir etapa de frete.
- Jefferson só após `TRANSFERENCIA_FINALIZADA`.
- Complementar transfere apenas quantidades novas.

## Riscos

- Layout Cigan indefinido — export genérico até spec; versionar snapshot.
- Estoque origem insuficiente na validação — bloquear com lista do que falta.
- Reverb não disponível em homologação — não bloqueia este plano (é PLAN-0068).
