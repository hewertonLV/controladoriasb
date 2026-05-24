# ADR-0068: API de pedidos, painel matriz loja×fruta e tempo real

**Data:** 2026-05-23
**Status:** Aceito (atualizado)
**Contexto:** Captação via app + supervisão web ([ADR-0066](ADR-0066-captacao-pedido-romaneios-fechamento-diario.md)); histórico em [ADR-0069](ADR-0069-pedido-historico-alteracoes.md)

## Contexto

Pedidos chegam pelo **aplicativo** (API). A equipe no **galpão** precisa de uma tela web com aparência e comportamento de **planilha Excel**: linhas = **lojas (clientes)**, colunas = **frutas**, células = quantidade (e preço quando aplicável) do dia/lote. Cada usuário vê apenas o **galpão ao qual está vinculado**. Alterações do app devem aparecer **instantaneamente** para quem tiver a tela aberta; a web edita as mesmas células. Sem botão “Salvar”: persiste ao **sair da célula**, com indicador de **sincronização** com o servidor.

## Decisão

### API REST (app e web)

- `api/v1` com **Sanctum**; escopo por `id_unidade_galpao` (e faturamento) do usuário.
- Recursos: pedidos/itens; endpoint de **célula** `PUT/PATCH .../captacao/{lote}/celula` (cliente, fruta, campos alterados) para autosave da matriz.
- `version` / `updated_at` por item para concorrência; conflito → 409 e célula em estado `erro` na UI.
- Toda mutação grava histórico ([ADR-0069](ADR-0069-pedido-historico-alteracoes.md)) com `origem` = `APP` ou `WEB`.
- Respeitar travas do lote ([ADR-0067](ADR-0067-pipeline-transferencia-lucas-venda-jefferson.md)) e bloqueio pós [ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md) (sem novos pedidos; qtd/rota travados até Lucas iniciar transferência).

### App móvel

- Mantém **lista/histórico** dos pedidos lançados pelo usuário (ou dispositivo) no galpão do dia.
- Permite **alteração** dos mesmos campos permitidos pela API (enquanto travas não bloquearem).
- **Precificação na captação:** custo de referência (leitura), preço de venda por UM e margem — [ADR-0073](ADR-0073-captacao-app-custo-preco-margem-um.md).
- Alterações do app disparam os mesmos eventos de broadcast da web.

### Painel web — matriz estilo Excel

- Rota admin dedicada (ex.: `/admin/captacao/matriz`).
- **Eixo vertical:** clientes/lojas do escopo do galpão (ordenção configurável).
- **Eixo horizontal:** frutas da **união dinâmica** dos vínculos cliente×fruta das lojas na captação ([ADR-0071](ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md)) — nunca as 500+ colunas do catálogo inteiro.
- **Célula:** representa o item `(lote, cliente, fruta)` — exibir/editar quantidade; preço em mesma célula (dois campos) ou sub-linha conforme UX; célula vazia = sem pedido ou quantidade zero.
- Usuário só acessa matriz do **galpão vinculado** (`UnidadeNegocioAccessService` / vínculo explícito usuário↔galpão).
- Biblioteca de grade tipo planilha (ex.: Handsontable, Jspreadsheet ou AG Grid) com navegação por teclado e foco por célula.

### Autosave e status de sincronização

- **Sem botão Salvar:** ao `blur` / sair da célula, enviar PATCH se valor mudou.
- Estados visíveis por célula (ou ícone na barra da linha):
  - `sincronizado` — persistido no servidor;
  - `pendente` / `sincronizando` — fila local ou request em flight;
  - `erro` — falha de rede/validação; permitir retry ao reeditar.
- Debounce opcional apenas para tecla (ex.: 300ms) antes de marcar `pendente`; commit no blur.

### Tempo real

- **Laravel Reverb** + **Echo**; canal privado por `captacao_lote_id` (ou galpão+data).
- Eventos: `PedidoItemAtualizado`, `PedidoItemCriado`, `LoteStatusAlterado` — atualizar célula remota sem recarregar página; não sobrescrever célula em edição local (lock otimista na célula focada).
- Fallback: polling 30s se WebSocket indisponível.

### Edição cruzada app ↔ web

- Mesma fonte de verdade no servidor; broadcast para todos os clientes do lote.
- Web pode alterar o que o app lançou e vice-versa; histórico registra autor e origem.

## Alternativas consideradas

- **Lista tabular (uma linha por pedido)** — rejeitada; operação pediu visão matriz loja×fruta.
- **Botão Salvar global** — rejeitado; autosave no blur.
- **Somente polling** — rejeitado como única estratégia para sensação instantânea.
- **Planilha Google OT completo** — rejeitado no MVP; versão + broadcast + histórico suficiente.
- **Catálogo completo como colunas** — rejeitado; ver [ADR-0071](ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md).

## Consequências

- [PLAN-0068](../plans/PLAN-0068-api-pedidos-painel-tempo-real.md) detalha matriz, autosave e Echo; depende de [PLAN-0071](PLAN-0071-vinculo-cliente-fruta-matriz-dinamica.md) para colunas dinâmicas.
- Infra Reverb no deploy.
- Avaliar licença da biblioteca de grade escolhida (Handsontable vs open source).
