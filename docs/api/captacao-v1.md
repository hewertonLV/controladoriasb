# API Captação v1 (referência)

Contrato formal: [ADR-0081](../decisions/ADR-0081-aplicativo-mobile-captacao-contrato-api.md).

## Base

- Prefixo: `/api/v1/captacao`
- Autenticação atual (web): sessão Laravel + cookie (homologação)
- Autenticação app (futuro): Bearer Sanctum

## Endpoints implementados

### POST `/lotes/{lote}/pedidos`

Cria ou atualiza pedido com itens.

```json
{
  "id_cliente": 1,
  "id_captacao_rota": 2,
  "data_entrega": "2026-05-30",
  "itens": [
    {
      "id_fruta": 10,
      "quantidade": 5,
      "preco_venda": 12.50,
      "id_unidade_origem_fisica": 3
    }
  ]
}
```

Resposta `201`: `{ "pedido": { ... } }`

### GET `/lotes/{lote}/pedidos`

Lista pedidos do usuário autenticado no lote.

## Endpoints web (matriz)

- `GET /admin/captacao/lotes/{lote}/matriz/estado` — snapshot JSON para polling
- `PATCH /admin/captacao/lotes/{lote}/celula` — autosave célula (`quantidade` ou `incremento`)
