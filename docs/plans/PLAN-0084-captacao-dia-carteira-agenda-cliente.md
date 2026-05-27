# PLAN-0084: Captação por dia × carteira e agenda do cliente

**ADR:** [ADR-0084](../decisions/ADR-0084-captacao-dia-carteira-agenda-cliente.md)
**Data:** 2026-05-30
**Status:** Concluído

## Objetivo

Operar captação por **dia × carteira**, vincular clientes à carteira com agenda de criação/envio do pedido, listar todos os clientes da carteira na matriz e oferecer consulta de lojas sem pedido criado no dia.

## Pré-requisitos

- Módulo Captação v2 em produção ([PLAN-0083](../plans/PLAN-0083-romaneio-manual-matriz-e-pipeline-sequencial.md) concluído).

## Passos

1. **Migration** — `captacao_carteiras`, `cliente_captacao_agenda`, FKs em `clientes` e `captacao_lotes`; backfill carteiras; unique `(data, carteira)`.
2. **Domínio** — models, enum agenda, `CaptacaoCarteiraService`, ajuste `CaptacaoLoteService`.
3. **Admin carteiras** — CRUD mínimo e item de menu.
4. **Cliente** — select carteira + checkboxes dias criação/envio no formulário.
5. **Captação** — abrir lote por carteira; menu “Captação”; matriz lista todos clientes da carteira.
6. **Consulta** — tela/aba clientes sem pedido (somente leitura).
7. **Testes** — feature carteira, lote, consulta, matriz.

## Critério de conclusão

- Menu “Captação”; lote abre por carteira; cliente com agenda persistida; consulta sem pedido funcional; testes verdes.

## Riscos

- Lotes antigos sem carteira — mitigar backfill na migration.
- Romaneio manual — continua faturamento + galpão até evolução futura.
