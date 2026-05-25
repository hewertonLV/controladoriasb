# ADR-0086: Várias carteiras com o mesmo faturamento e galpão

**Data:** 2026-05-25
**Status:** Aceito
**Contexto:** Cadastro de carteiras de captação (ADR-0084)

## Contexto

A operação precisa segmentar lojas em mais de uma carteira comercial mesmo quando faturamento e galpão físico são os mesmos (ex.: “Carteira Barbalha 01” e “Carteira Litoral Barbalha 01” no mesmo par UN).

## Decisão

- **Permitir** N carteiras ativas/inativas com o mesmo `(id_unidade_negocio_faturamento, id_unidade_negocio_galpao)`.
- Remover unique `cap_cart_fat_galp_uq`; manter índice não único `cap_cart_fat_galp_ix` para consultas.
- Diferenciação operacional pelo **`nome`** da carteira e pelos **clientes** vinculados (`clientes.id_captacao_carteira`).
- Lote de captação continua único por `(data_referencia, id_captacao_carteira)` — cada carteira abre seu próprio lote no dia.
- `garantirCarteira(fat, galpão)` (legado/romaneio sem carteira explícita): se já existir ao menos uma carteira no par, reutiliza a de **menor `id`**; só **cria** carteira automática quando não houver nenhuma. Fluxo normal de captação deve informar `id_captacao_carteira`.

## Alternativas consideradas

- **Uma carteira por par** — rejeitado; não atende divisão comercial de equipes/rotas no mesmo CD.
- **Unicidade só no nome** — rejeitado; nomes podem repetir entre contextos; vínculo de cliente resolve o escopo.

## Consequências

- [PLAN-0086](../plans/PLAN-0086-multiplas-carteiras-mesmo-faturamento-galpao.md).
- Atualizar ADR-0084 (remover regra de unicidade do par).
- Romaneio manual sem seleção de carteira: comportamento legado (primeira carteira do par) — preferir evoluir UI para escolher carteira se houver ambiguidade.
