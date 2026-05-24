# PLAN-0068: API, matriz loja×fruta, autosave e tempo real

**ADR:** [ADR-0068](../decisions/ADR-0068-api-pedidos-painel-matriz-tempo-real.md), [ADR-0069](../decisions/ADR-0069-pedido-historico-alteracoes.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

API para o app, painel web em formato planilha (lojas × frutas), autosave ao sair da célula com status de sincronização, broadcast instantâneo e histórico completo de alterações.

## Pré-requisitos

- Lote + pedido/item do [PLAN-0066](PLAN-0066-captacao-pedido-romaneios-fechamento-diario.md) passo 3.
- [PLAN-0071](PLAN-0071-vinculo-cliente-fruta-matriz-dinamica.md) (vínculo e colunas dinâmicas).
- [PLAN-0073](PLAN-0073-captacao-app-custo-preco-margem-um.md) (custo, preço/UM, margem).
- Sanctum; Reverb; vínculo usuário↔galpão definido.

## Passos

1. **Histórico** — migrations `pedido_historicos`, `pedido_item_historicos`; `PedidoAuditoriaService`; testes de gravação em create/update.
2. **Sanctum + escopo galpão** — policies; app só envia para galpão permitido.
3. **API v1** — CRUD pedido/item; endpoint `PATCH celula`; origem APP/WEB; versionamento 409.
4. **Eventos + Reverb** — `PedidoItemAtualizado`, etc.; canal `captacao.{loteId}`.
5. **Endpoint matriz** — `GET captacao/{lote}/matriz` retorna grid (lojas, frutas, células com qtd/preço/version).
6. **Tela web matriz** — grade Excel-like; eixo Y lojas, X frutas; filtro só galpão do usuário.
7. **Autosave** — blur → PATCH celula; estados `sincronizado` | `pendente` | `sincronizando` | `erro` na UI.
8. **Echo** — merge remoto em célula; não sobrescrever célula em foco local.
9. **Travas ADR-0067** — desabilitar células (qtd/preço) conforme status do lote.
10. **App** — histórico de lançamentos do captador; edição via mesma API.
11. **Testes** — Feature API; histórico; conflito de versão; policy galpão.

## Critério de conclusão

- Pedido do app aparece na matriz web sem refresh manual.
- Edição web persiste ao sair da célula com indicador de sync.
- Toda alteração gera linha em `pedido_item_historicos`.
- Usuário de outro galpão não vê a matriz alheia.

## Riscos

- Licença da grid comercial — validar Handsontable vs Jspreadsheet CE.
- Matriz grande (muitas lojas×frutas) — paginação virtual ou lazy load de colunas.

## Ordem sugerida

Após passo 3 do PLAN-0066; paralelo aos romaneios (passos 4–7 do 0066).
