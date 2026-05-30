# PLAN-0174: PDF do romaneio por rota concluída na matriz

**ADR:** [ADR-0174](../decisions/ADR-0174-romaneio-rota-pdf-matriz.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Permitir download do romaneio de viagem em PDF na aba de cada rota concluída da matriz, com nome `{rota} - {motorista}.pdf`.

## Pré-requisitos

- ADR-0173 (abas por rota vinculada)
- `RomaneioCarregamentoService::previewPorRotas()`
- DomPDF (`barryvdh/laravel-dompdf`)

## Passos

1. **Serviço PDF** — `RomaneioRotaPdfService` + helper de nome de arquivo
2. **View PDF** — `resources/views/admin/captacao/pdf/romaneio-rota.blade.php` alinhada ao modelo
3. **Rota e controller** — GET `romaneio.pdf` em `CaptacaoMatrizController`
4. **UI** — botão no cabeçalho da aba (Blade + JS tempo real)
5. **Testes** — 404 rota aberta; 200 + Content-Disposition rota concluída

## Critério de conclusão

- Rota concluída exibe botão de download na matriz
- PDF baixa com nome sanitizado `{rota} - {motorista}.pdf`
- Rota não concluída retorna 404
- Testes de feature passam

## Riscos

- Layout DomPDF divergir do modelo — mitigar com view dedicada e revisão visual
- Motorista não informado — mitigar com sufixo `Sem motorista` no filename
