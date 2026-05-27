# ADR-0081: Aplicativo mobile de captação — contrato de API (fase posterior)

**Data:** 2026-05-29
**Status:** Aceito
**Contexto:** [ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md); implementação do app **fora do escopo imediato**

## Contexto

O app móvel será desenvolvido **após** o núcleo web e o pipeline operacional estarem estáveis. A API REST já existe parcialmente em `routes/api.php` com autenticação de sessão web; na fase do app será adotado **Laravel Sanctum** (tokens por dispositivo/usuário).

## Decisão

### Autenticação (fase app)

- `POST /api/v1/auth/login` — email, password, device_name → `{ token, user }`
- Header: `Authorization: Bearer {token}`
- Escopo: usuário vinculado a `id_unidade_negocio_galpao` (e indiretamente faturamento)

### Lote do dia

| Método | Rota | Entrada | Saída |
|--------|------|---------|-------|
| POST | `/api/v1/captacao/lotes/abrir` | `data_referencia`, `id_unidade_negocio_faturamento`, `id_unidade_negocio_galpao` | `{ lote: { id, status, ... } }` |
| GET | `/api/v1/captacao/lotes/{id}` | — | lote + status + travas |

### Pedidos

| Método | Rota | Corpo | Regras |
|--------|------|-------|--------|
| POST | `/api/v1/captacao/lotes/{lote}/pedidos` | `id_cliente`, `id_captacao_rota?`, `data_entrega?`, `itens[]: { id_fruta, quantidade, preco_venda?, id_unidade_origem_fisica? }` | `origem=APP`; histórico obrigatório |
| GET | `/api/v1/captacao/lotes/{lote}/pedidos` | — | pedidos do usuário/dispositivo no lote |
| PATCH | `/api/v1/captacao/lotes/{lote}/celula` | `id_cliente`, `id_fruta`, `quantidade` ou `incremento`, `version?` | 409 se `version` desatualizado |

### Precificação (leitura)

| Método | Rota | Saída |
|--------|------|-------|
| GET | `/api/v1/captacao/lotes/{lote}/precificacao/{fruta}` | `{ custo_referencia, margem se preco informado }` — [ADR-0073](ADR-0073-captacao-app-custo-preco-margem-um.md) |

### Catálogo auxiliar

| Método | Rota | Uso |
|--------|------|-----|
| GET | `/api/v1/captacao/clientes` | Lojas do faturamento do galpão |
| GET | `/api/v1/captacao/clientes/{id}/frutas` | Vínculos [ADR-0071](ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md) |
| GET | `/api/v1/captacao/rotas` | Rotas do galpão |

### O que o app **não** faz

- Finalizar captação, validar transferências, faturamento Cigan, finalizar vendas (web/Lucas/Jefferson)
- Romaneio manual, vínculo de frete, alertas comerciais

### Sincronização / tempo real

- App envia PATCH por célula ou POST pedido completo no blur/salvar
- Web matriz usa polling 2s em `/admin/captacao/lotes/{lote}/matriz/estado` até Reverb estar disponível
- Broadcast futuro: evento `PedidoItemAtualizado` (canal por `lote_id`)

## Alternativas consideradas

- **GraphQL** — rejeitado; REST alinhado ao Laravel e ao painel web
- **App offline-first completo** — fase 2; MVP online com retry em 409

## Consequências

- [PLAN-0081](../plans/PLAN-0081-aplicativo-mobile-captacao-contrato-api.md)
- Implementar Sanctum e rotas `auth/login` quando iniciar o app
- Documentação OpenAPI opcional em `docs/api/captacao-v1.md`
