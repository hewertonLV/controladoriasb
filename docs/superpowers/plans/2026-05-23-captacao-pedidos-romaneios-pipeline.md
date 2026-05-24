# PACOTE-0066: Captação e pipeline operacional — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar captação de pedidos (app + matriz web), romaneios, pipeline Lucas/Jefferson com export Cigan, alertas comerciais (fase 2) e depreciação operacional de import de movimentação.

**Architecture:** Novo bounded context `Captacao` em `app/Models/Captacao`, `app/Services/Captacao`, controllers admin + API v1; integra com `Movimentacoes` só nas etapas Lucas/Jefferson. ADRs 0066–0080; spec em [2026-05-23-captacao-pedidos-romaneios-pipeline-design.md](../specs/2026-05-23-captacao-pedidos-romaneios-pipeline-design.md).

**Tech Stack:** Laravel, Sanctum, Reverb/Echo, Pest/PHPUnit, Blade + Vite, padrões existentes (`Permissions`, `UnidadeNegocioAccessService`).

**Pré-implementação:** `.cursor/skills/pre-implementacao-gate/SKILL.md` em cada fase; TDD por feature test.

---

## Mapa de fases

| Fase | PLAN(s) | Entrega testável |
|------|---------|------------------|
| 1 | [0066](../../plans/PLAN-0066-captacao-pedido-romaneios-fechamento-diario.md) | Lotes, rotas, pedidos, romaneios prévia |
| 2 | [0071](../../plans/PLAN-0071-vinculo-cliente-fruta-matriz-dinamica.md) | Vínculo cliente×fruta |
| 3 | [0068](../../plans/PLAN-0068-api-pedidos-painel-tempo-real.md), [0069](../../plans/PLAN-0069-pedido-historico-alteracoes.md), [0073](../../plans/PLAN-0073-captacao-app-custo-preco-margem-um.md), [0077](../../plans/PLAN-0077-custo-embutido-pm-e-co-venda-hub-praca.md) | API, matriz, histórico, precificação |
| 4 | [0070](../../plans/PLAN-0070-finalizar-captacao-unidade-faturamento.md) | Finalizar captação faturamento |
| 5 | [0067](../../plans/PLAN-0067-pipeline-transferencia-lucas-venda-jefferson.md), [0072](../../plans/PLAN-0072-vinculo-frete-pos-transferencia-lote.md), [0075](../../plans/PLAN-0075-transferencia-gerencial-lucas-escopo-unidade.md), [0076](../../plans/PLAN-0076-calendario-captacao-d0-faturamento-d1.md) | Pipeline Lucas/Jefferson |
| 6 | [0074](../../plans/PLAN-0074-romaneio-manual-abastecimento-sem-captacao.md) | Romaneio manual |
| 7 | Cigan spec (futuro) | Layout export definitivo |
| 8 | [0078](../../plans/PLAN-0078-alertas-lojas-sem-pedido-dia-semana.md), [0080](../../plans/PLAN-0080-alertas-fruta-habitual-ausente-romaneio.md) | Alertas comerciais |
| Go-live | [0079](../../plans/PLAN-0079-importacao-apenas-cadastro-sem-movimentacoes.md) | Banners import legado |

---

## Fase 1 — Núcleo captação (PLAN-0066)

### Task 1: Migrations e enums de status

**Files:**
- Create: `database/migrations/2026_05_23_100000_create_captacao_tables.php`
- Create: `app/Enums/CaptacaoLoteStatus.php`
- Create: `app/Enums/CaptacaoFaturamentoDiaStatus.php`

- [ ] **Step 1: Feature test esqueleto**

```php
// tests/Feature/Admin/Captacao/CaptacaoLoteTest.php
public function test_abre_lote_do_dia_por_galpao(): void
{
    $galpao = UnidadeNegocio::factory()->galpao()->create();
    $response = $this->actingAsAdmin()->post(route('admin.captacao.lotes.store'), [
        'data_referencia' => '2026-05-23',
        'id_unidade_negocio_galpao' => $galpao->id,
    ]);
    $response->assertRedirect();
    $this->assertDatabaseHas('captacao_lotes', [
        'id_unidade_negocio_galpao' => $galpao->id,
        'status' => CaptacaoLoteStatus::CAPTACAO_EM_ANDAMENTO->value,
    ]);
}
```

- [ ] **Step 2: Rodar teste — deve falhar**

Run: `php artisan test --filter=test_abre_lote_do_dia_por_galpao`
Expected: FAIL (tabela/rota inexistente)

- [ ] **Step 3: Migration** — tabelas: `captacao_faturamento_dias`, `captacao_lotes`, `captacao_rotas`, `pedidos`, `pedido_itens`, `romaneio_carregamento_linhas`, `romaneio_abastecimento_linhas` (FKs para `unidades_negocio`, `clientes`, `produtos`).

- [ ] **Step 4: Models** — `app/Models/Captacao/CaptacaoLote.php`, `Pedido.php`, `PedidoItem.php`, `CaptacaoRota.php`, relations.

- [ ] **Step 5: Teste passa**

Run: `php artisan test tests/Feature/Admin/Captacao/CaptacaoLoteTest.php`

### Task 2: Rotas CRUD e pedidos sem movimentação

**Files:**
- Create: `app/Http/Controllers/Admin/Captacao/CaptacaoLoteController.php`
- Create: `app/Http/Controllers/Admin/Captacao/CaptacaoRotaController.php`
- Create: `app/Http/Controllers/Admin/Captacao/PedidoController.php`
- Create: `app/Services/Captacao/CaptacaoLoteService.php`, `PedidoService.php`
- Modify: `routes/web.php` — grupo `admin/captacao`
- Modify: `app/Enums/Permissions.php` — constantes `captacao.*`

- [ ] Test: criar pedido com item **não** cria `movimentacoes`
- [ ] Implementar store/update pedido + item com `id_unidade_faturamento`, `id_unidade_galpao`, `id_origem_fisica`
- [ ] Policy escopo via `UnidadeNegocioAccessService`

### Task 3: Romaneios prévia

**Files:**
- Create: `app/Services/Captacao/RomaneioCarregamentoService.php`
- Create: `app/Services/Captacao/RomaneioAbastecimentoService.php`
- Create: `resources/views/admin/captacao/lotes/show.blade.php` — abas Romaneio 1 e 2

- [ ] Test: após 2 pedidos com rotas, romaneio 1 agrupa por cliente/rota
- [ ] Test: romaneio 2 separa estoque galpão vs a receber
- [ ] Recalcular prévia em observer/listener ao salvar `PedidoItem`

**Critério fase 1:** [PLAN-0066](../../plans/PLAN-0066-captacao-pedido-romaneios-fechamento-diario.md) critério de conclusão.

---

## Fase 2 — Vínculo cliente×fruta (PLAN-0071)

**Files:**
- Create: `database/migrations/2026_05_24_100000_create_cliente_fruta_vinculos_table.php`
- Create: `app/Models/ClienteFrutaVinculo.php`
- Create: `app/Http/Controllers/Admin/ClienteFrutaVinculoController.php`
- Modify: `resources/views/admin/clientes/_form.blade.php` ou tela dedicada

- [ ] CRUD vínculo; test: matriz futura só inclui frutas vinculadas às lojas do lote
- [ ] Seed opcional para ambiente dev

---

## Fase 3 — API, matriz, histórico, custo (PLAN-0068, 0069, 0073, 0077)

### Task 3a: Histórico (0069 primeiro)

**Files:**
- Create: `database/migrations/2026_05_24_110000_create_pedido_historicos_tables.php`
- Create: `app/Observers/PedidoItemObserver.php` — grava histórico `APP`/`WEB`

### Task 3b: API Sanctum

**Files:**
- Modify: `routes/api.php` — prefix `v1/captacao`
- Create: `app/Http/Controllers/Api/V1/Captacao/PedidoController.php`
- Create: `app/Http/Requests/Api/V1/UpsertPedidoItemRequest.php`

- [ ] Test API: POST item com token Sanctum; 403 fora do galpão do usuário
- [ ] Test: mutação grava `pedido_item_historicos`

### Task 3c: Matriz web

**Files:**
- Create: `app/Http/Controllers/Admin/Captacao/CaptacaoMatrizController.php`
- Create: `resources/views/admin/captacao/matriz/index.blade.php`
- Create: `resources/js/captacao-matriz.js` — grade + autosave PATCH célula
- Create: `app/Http/Controllers/Admin/Captacao/CaptacaoCelulaController.php` — `updateCelula`

- [ ] Test: PATCH célula cria/atualiza item; colunas = união vínculos ([0071](../../decisions/ADR-0071-vinculo-cliente-fruta-matriz-dinamica.md))
- [ ] Test: 409 em `version` desatualizado
- [ ] Integrar Reverb: `PedidoItemAtualizado` event

### Task 3d: Custo referência e margem

**Files:**
- Create: `app/Services/Captacao/CaptacaoPrecificacaoService.php` — PM galpão ([0077](../../decisions/ADR-0077-custo-embutido-pm-e-co-venda-hub-praca.md))
- Modify: API resource — expor `custo_referencia`, `margem_calculada`

- [ ] Test: custo ref = PM; não inclui CO praça na captação

---

## Fase 4 — Finalizar captação (PLAN-0070)

**Files:**
- Create: `app/Actions/Captacao/FinalizarCaptacaoFaturamentoAction.php`
- Modify: `CaptacaoLoteService` — transição `AGUARDANDO_TRANSFERENCIA_CIGAN`
- Modify: `resources/views/admin/captacao/faturamento-dias/index.blade.php`

- [ ] Test: sem permissão `captacao.faturamento.finalizar` → 403
- [ ] Test: pedido sem rota → bloqueia finalizar
- [ ] Test: após finalizar, POST novo pedido → 422
- [ ] Test: consolida romaneios 1 e 2 (snapshot)

---

## Fase 5 — Pipeline Lucas/Jefferson (PLAN-0067, 0072, 0075, 0076)

**Files:**
- Create: `app/Actions/Captacao/IniciarTransferenciaCiganAction.php`
- Create: `app/Actions/Captacao/ValidarTransferenciasGerenciaisLoteAction.php`
- Create: `app/Actions/Captacao/VincularFreteLoteAction.php`
- Create: `app/Actions/Captacao/ConcluirEtapaFreteLoteAction.php`
- Create: `app/Actions/Captacao/IniciarFaturamentoCiganAction.php`
- Create: `app/Actions/Captacao/FinalizarVendasLoteAction.php`
- Create: `app/Services/Captacao/GerarArquivoCiganTransferenciaService.php` (placeholder CSV)
- Create: `app/Services/Captacao/GerarArquivoCiganVendasService.php`
- Modify: `app/Services/Movimentacoes/TransferenciaMovimentacaoService.php` — chamada na validação
- Modify: `app/Services/Movimentacoes/VendaMovimentacaoService.php` — CO praça em saída HUB ([0063](../../decisions/ADR-0063-venda-hub-co-unidade-faturamento.md))

- [ ] Test fluxo completo: finalizar captação → iniciar transf → validar → frete opcional → concluir frete → iniciar fat → finalizar venda → `movimentacoes` tipo venda
- [ ] Test: qty bloqueada após iniciar transferência
- [ ] Test: preço bloqueado após concluir frete
- [ ] Test: `data_movimentacao` D+1 quando configurado ([0076](../../decisions/ADR-0076-calendario-captacao-d0-faturamento-d1.md))
- [ ] Test: transferência gerencial HUB→galpão na validação ([0075](../../decisions/ADR-0075-transferencia-gerencial-lucas-escopo-unidade.md))

---

## Fase 6 — Romaneio manual (PLAN-0074)

**Files:**
- Create: `app/Actions/Captacao/CriarRomaneioManualAction.php`
- Modify: `CaptacaoLoteService` — tipo `ROMANEIO_MANUAL`

- [ ] Test: fluxo para `TRANSFERENCIA_FINALIZADA` sem etapas Jefferson
- [ ] Test: botões faturamento ocultos para tipo manual

---

## Fase 7 — Layout Cigan (pendente)

- [ ] ADR futura com colunas; substituir placeholder nos `GerarArquivoCigan*` services
- [ ] Test snapshot arquivo versionado

---

## Fase 8 — Alertas comerciais (PLAN-0078, PLAN-0080)

**Files:**
- Create: `app/Services/Captacao/Alertas/LojasSemPedidoDiaSemanaQuery.php`
- Create: `app/Services/Captacao/Alertas/FrutasHabituaisAusentesQuery.php`
- Create: `app/Http/Controllers/Admin/Captacao/AlertasComerciaisController.php`
- Create: `resources/views/admin/captacao/alertas/index.blade.php` — abas

- [ ] Test 0078: loja 3/4 terças, não pediu hoje → lista
- [ ] Test 0080: loja pediu maçã; banana habitual → alerta banana
- [ ] Test: loja sem pedido hoje **não** aparece na aba frutas

---

## Go-live — Import legado (PLAN-0079)

**Files:**
- Modify: views import `VendaImportacaoController`, `TransferenciaImportacaoController`, `EstoqueImportacaoController` — banner legado
- Modify: `docs/SISTEMA_CONTEXTO.md`

- [ ] Sem remover rotas/controllers
- [ ] Test: rotas import ainda respondem 200

---

## Verificação final do pacote

```bash
php artisan migrate --force
php artisan test tests/Feature/Admin/Captacao/
php artisan test tests/Feature/Admin/Movimentacoes/VendaMovimentacaoTest.php  # regressão CO HUB
```

---

## Self-review (plano × spec)

| Requisito spec | Fase |
|----------------|------|
| Lote por galpão, sem movimentação na captação | 1 |
| Romaneios prévia | 1 |
| Matriz dinâmica + API | 2–3 |
| Finalizar faturamento | 4 |
| Lucas/Jefferson + travas | 5 |
| Romaneio manual sem Jefferson | 6 |
| CO praça venda HUB | 5 (VendaMovimentacaoService) |
| Alertas 0078/0080 | 8 |
| Import legado | Go-live |
| Layout Cigan | 7 (pendente) |

**Gaps intencionais:** UI app mobile; reabrir captação; remoção código import.

---

## Documentos de referência

- Spec: `docs/superpowers/specs/2026-05-23-captacao-pedidos-romaneios-pipeline-design.md`
- Índice: `docs/pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md`
- ADRs: `docs/decisions/ADR-0066` … `ADR-0080`
- PLANs granulares: `docs/plans/PLAN-NNNN-*.md`
