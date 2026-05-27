<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasVendaOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Enums\StatusRecebimentoTransferencia;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\MovimentacaoHistorico;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class VendaMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_create_carrega_origem_cliente_e_unidade_faturamento_sem_erro(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $html = $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CRIAR]))
            ->get(route('admin.movimentacoes.vendas.create'))
            ->assertOk()
            ->getContent();
        $htmlDecodificado = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString($c['unidade']->nome, $htmlDecodificado);
        $this->assertStringContainsString($c['empresa_cliente']->nomeExibicao(), $htmlDecodificado);
        $this->assertStringNotContainsString('name="id_unidade_negocio_faturamento"', (string) $html);
        $this->assertStringContainsString('name="id_unidade_negocio_estoque"', (string) $html);
    }

    public function test_usuario_com_permissao_cria_venda_multi_item_e_calcula_estoque_valores_e_resultado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-17 18:55:00'));

        $this->seedBase();
        $c = $this->cenarioBase();
        $fruta2 = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => 0,
            'entrada_externo' => 0,
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create([
            'kg_por_unidade_medicao' => 5,
        ]);

        $this->registrarCompra($c, '10', '500,00');
        $this->registrarCompra(array_merge($c, ['fruta' => $fruta2]), '4', '200,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => ' NF-100 ',
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'observacao' => 'Venda com dois itens.',
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300,00'],
                ['id_fruta' => $fruta2->id, 'qtd_fruta_um' => '1', 'valor_nf_total' => '80,00'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('vendas_notas', [
            'numero_nf' => 'NF-100',
            'valor_total_nf' => '380.00',
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            'id_unidade_negocio_faturamento' => $c['unidade']->id,
            'data_emissao' => '2026-05-17 18:55:00',
        ]);

        $vendas = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $vendas);
        $venda = $vendas->firstWhere('id_fruta', $c['fruta']->id);
        $this->assertNotNull($venda);
        $this->assertSame('300.00', (string) $venda->valor_nf_total);
        $this->assertSame('150.00', (string) $venda->valor_nf_um);
        $this->assertSame('15.00', (string) $venda->valor_nf_kg);
        $this->assertSame('100.00', (string) $venda->valor_custo_saida);
        $this->assertSame('100.00', (string) $venda->valor_total_movimentacao);
        $this->assertSame('200.00', (string) $venda->resultado_movimentacao);
        $this->assertSame('5.00', (string) $venda->preco_medio_fruta_kg);
        $this->assertSame($c['unidade']->id, (int) $venda->id_unidade_negocio_faturamento);
        $this->assertSame('2026-05-17 18:55:00', $venda->data_movimentacao?->format('Y-m-d H:i:s'));
        $this->assertNull($venda->categoria_descarte_id);
        $this->assertSame('0.00', (string) $venda->valor_icms_total);
        $this->assertEstoque($c['unidade'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');
        $this->assertDatabaseHas('movimentacao_historicos', [
            'movimentacao_cadeia_raiz_id' => $venda->id,
            'origem' => MovimentacaoHistorico::ORIGEM_VENDA,
            'acao' => MovimentacaoHistorico::ACAO_REGISTRO_VENDA,
        ]);

        $usuarioRevisao = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_VENDAS_VISUALIZAR,
            Permissions::MOVIMENTACOES_VENDAS_EDITAR,
            Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN,
        ]);

        $html = $this->actingAs($usuarioRevisao)
            ->get(route('admin.movimentacoes.vendas.show', $venda))
            ->assertOk()
            ->assertSeeText('Frutas vendidas nesta venda')
            ->getContent();
        $htmlDecodificado = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString($c['fruta']->nome, $htmlDecodificado);
        $this->assertStringContainsString($fruta2->nome, $htmlDecodificado);
        $this->assertStringContainsString('Cancelar item', $htmlDecodificado);
        $this->assertStringContainsString('Cancelar venda completa', $htmlDecodificado);

        $editHtml = $this->actingAs($usuarioRevisao)
            ->get(route('admin.movimentacoes.vendas.edit', $venda))
            ->assertOk()
            ->getContent();
        $editHtmlDecodificado = html_entity_decode((string) $editHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString($c['fruta']->nome, $editHtmlDecodificado);
        $this->assertStringContainsString($fruta2->nome, $editHtmlDecodificado);
        $this->assertStringContainsString('Corrigir este item', $editHtmlDecodificado);

        $this->cancelarVendaAdmin($venda);
        $vendas->each->refresh();
        $this->assertTrue($vendas->every(fn (Movimentacao $item): bool => $item->status_registro === MovimentacaoStatusRegistro::CANCELADO->value));
        $this->assertDatabaseHas('vendas_notas', [
            'id' => $venda->venda_nota_id,
            'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
        ]);
        $this->assertEstoque($c['unidade'], $c['fruta'], '100.00', '10.00', '5.00', '50.00', '500.00');
        $this->assertEstoque($c['unidade'], $fruta2, '20.00', '4.00', '10.00', '50.00', '200.00');

        Carbon::setTestNow();
    }

    public function test_venda_em_pe_aplica_icms_percentual_conforme_estado_do_cliente(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $unidadeCe = UnidadeNegocio::factory()->create(['id_estado' => Estado::ID_CEARA, 'possui_estoque' => false]);
        $clienteFora = Cliente::factory()->create(['id_unidade_negocio' => $unidadeCe->id]);
        $empresaClienteFora = $clienteFora->registroCorporativo()->firstOrFail();

        $fruta = Fruta::factory()->comIcmsPernambuco()->create([
            'kg_por_unidade_medicao' => 10,
        ]);
        $c['fruta'] = $fruta;

        $this->registrarCompra($c, '10', '500,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-PE-ICMS',
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $empresaClienteFora->id,
            'itens' => [
                ['id_fruta' => $fruta->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '1000,00'],
            ],
        ])->assertCreated();

        $venda = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('id_fruta', $fruta->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame('120.00', (string) $venda->valor_icms_total);
        $this->assertSame('6.00', (string) $venda->valor_icms_kg);
        $this->assertSame('60.00', (string) $venda->valor_icms_um);
    }

    public function test_cancelamento_venda_restitui_estoque_quando_nao_ha_movimentacao_posterior(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '2', '300,00');

        $this->assertEstoque($c['unidade'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');

        $this->cancelarVendaAdmin($venda);

        $this->assertEstoque($c['unidade'], $c['fruta'], '100.00', '10.00', '5.00', '50.00', '500.00');
    }

    public function test_cancelamento_venda_com_compra_posterior_nao_infla_estoque(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '2', '300,00');
        $this->registrarCompra($c, '3', '150,00');

        $this->assertEstoque($c['unidade'], $c['fruta'], '110.00', '11.00', '5.00', '50.00', '550.00');

        $this->cancelarVendaAdmin($venda);

        $this->assertEstoque($c['unidade'], $c['fruta'], '110.00', '11.00', '5.00', '50.00', '550.00');
    }

    public function test_cancelamento_venda_com_venda_posterior_recompoe_estoque(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();

        $this->registrarCompra($c, '10', '500,00');
        $venda1 = $this->registrarVenda($c, '2', '300,00');
        $this->registrarVenda($c, '1', '150,00');

        $this->assertEstoque($c['unidade'], $c['fruta'], '70.00', '7.00', '5.00', '50.00', '350.00');

        $this->cancelarVendaAdmin($venda1);

        $this->assertEstoque($c['unidade'], $c['fruta'], '90.00', '9.00', '5.00', '50.00', '450.00');
    }

    public function test_cancelamento_individual_de_item_da_venda_nao_cancela_demais_frutas(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $fruta2 = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => 0,
            'entrada_externo' => 0,
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create([
            'kg_por_unidade_medicao' => 5,
        ]);

        $this->registrarCompra($c, '10', '500,00');
        $this->registrarCompra(array_merge($c, ['fruta' => $fruta2]), '4', '200,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-ITEM',
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300,00'],
                ['id_fruta' => $fruta2->id, 'qtd_fruta_um' => '1', 'valor_nf_total' => '80,00'],
            ],
        ])->assertCreated();

        $vendaCancelada = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('id_fruta', $c['fruta']->id)
            ->firstOrFail();
        $vendaMantida = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('id_fruta', $fruta2->id)
            ->firstOrFail();

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-item-admin', $vendaCancelada), [
                'motivo' => 'Cancelamento apenas da fruta selecionada.',
            ])
            ->assertOk();

        $vendaCancelada->refresh();
        $vendaMantida->refresh();

        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $vendaCancelada->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $vendaMantida->status_registro);
        $this->assertDatabaseHas('vendas_notas', [
            'id' => $vendaCancelada->venda_nota_id,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
        ]);
        $this->assertEstoque($c['unidade'], $c['fruta'], '100.00', '10.00', '5.00', '50.00', '500.00');
        $this->assertEstoque($c['unidade'], $fruta2, '15.00', '3.00', '10.00', '50.00', '150.00');

        $html = $this->actingAs($this->userWithPermissions([
            Permissions::MOVIMENTACOES_VENDAS_VISUALIZAR,
            Permissions::MOVIMENTACOES_VENDAS_EDITAR,
            Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN,
        ]))
            ->get(route('admin.movimentacoes.vendas.show', $vendaMantida))
            ->assertOk()
            ->getContent();
        $htmlDecodificado = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringContainsString($c['fruta']->nome, $htmlDecodificado);
        $this->assertStringContainsString($fruta2->nome, $htmlDecodificado);
        $this->assertStringContainsString(MovimentacaoStatusRegistro::CANCELADO->value, $htmlDecodificado);
    }

    public function test_venda_abate_apenas_desconto_nf_do_cliente_no_valor_vendido_real(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase(clienteOverrides: [
            'desconto_nf' => '10.00',
        ]);

        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '2', '300,00');

        $this->assertSame('270.00', (string) $venda->valor_nf_total);
        $this->assertSame('135.00', (string) $venda->valor_nf_um);
        $this->assertSame('13.50', (string) $venda->valor_nf_kg);
        $this->assertSame('100.00', (string) $venda->valor_custo_saida);
        $this->assertSame('170.00', (string) $venda->resultado_movimentacao);
        $this->assertDatabaseHas('vendas_notas', [
            'id' => $venda->venda_nota_id,
            'valor_total_nf' => '270.00',
        ]);

        $this->actingAs($this->movimentacoesVendasUsuario())->putJson(route('admin.movimentacoes.vendas.update', $venda), [
            'numero_nf' => 'NF-TESTE',
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '2',
            'valor_nf_total' => '400,00',
            'motivo_substituicao' => 'Ajuste do valor vendido bruto.',
        ])->assertOk();

        $vendaCorrigida = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame('360.00', (string) $vendaCorrigida->valor_nf_total);
        $this->assertSame('260.00', (string) $vendaCorrigida->resultado_movimentacao);
    }

    public function test_criacao_rejeita_data_emissao_informada_pelo_usuario(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), array_merge($this->payloadVenda($c), [
            'data_emissao' => '2020-01-01 10:00:00',
        ]))->assertJsonValidationErrors(['data_emissao']);
    }

    public function test_venda_origem_producao_aplica_custo_operacional_hub_na_margem(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $c['unidade']->forceFill(['is_unidade_producao' => true])->save();

        $hub = UnidadeNegocio::factory()->create([
            'is_hub' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => '1.50',
        ]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $hub->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $hub->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);

        $this->registrarCompra($c, '10', '500,00');

        $venda = $this->registrarVendaProducao($c, '2', '300,00', $hub, true);

        $this->assertSame('1.50', (string) $venda->valor_custo_operacional);
        $this->assertNotNull($venda->id_custo_operacional);
        $this->assertSame('100.00', (string) $venda->valor_custo_saida);
        $this->assertSame('170.00', (string) $venda->resultado_movimentacao);
    }

    public function test_venda_origem_producao_sem_hub_co_mantem_margem_sem_co(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $c['unidade']->forceFill(['is_unidade_producao' => true])->save();
        $this->registrarCompra($c, '10', '500,00');

        $venda = $this->registrarVendaProducao($c, '2', '300,00', null, false);

        $this->assertSame('0.00', (string) $venda->valor_custo_operacional);
        $this->assertNull($venda->id_custo_operacional);
        $this->assertSame('200.00', (string) $venda->resultado_movimentacao);
    }

    public function test_venda_barbalha_nf_com_centro_galpao_aplica_co_galpao_e_debita_estoque_galpao(): void
    {
        $this->seedBase();
        $c = $this->cenarioBarbalhaComGalpao();
        $this->registrarCompraGalpao($c, '10', '500,00');

        HistoricoCOUnNg::query()->where('id_unidade_negocio', $c['galpao']->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'custo_operacional' => '2.00',
            'status_position' => true,
        ]);
        $c['galpao']->forceFill(['custo_operacional' => '2.00'])->save();

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-GALPAO-1',
            'id_empresa_origem' => $c['empresa_barbalha']->id,
            'id_unidade_negocio_centro_resultado' => $c['galpao']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300,00'],
            ],
        ])->assertCreated();

        $venda = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->latest('id')
            ->firstOrFail();

        $coGalpao = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $c['galpao']->id)
            ->where('status_position', true)
            ->firstOrFail();

        $this->assertSame($c['barbalha']->id, (int) $venda->id_unidade_negocio_faturamento);
        $this->assertSame($c['galpao']->id, (int) $venda->id_unidade_negocio_centro_resultado);
        $this->assertNull($venda->id_unidade_negocio_estoque);
        $this->assertSame('2.00', (string) $venda->valor_custo_operacional);
        $this->assertSame((int) $coGalpao->id, (int) $venda->id_custo_operacional);
        $this->assertSame('100.00', (string) $venda->valor_custo_saida);
        $this->assertSame('160.00', (string) $venda->resultado_movimentacao);
        $this->assertEstoque($c['galpao'], $c['fruta'], '80.00', '8.00', '5.00', '50.00', '400.00');
        $this->assertFalse(
            Estoque::query()->where('id_unidade_negocio', $c['barbalha']->id)->where('id_fruta', $c['fruta']->id)->exists(),
        );
    }

    public function test_venda_comercial_com_saida_fisica_hub_embuti_co_faturamento_no_custo_saida(): void
    {
        $this->seedBase();
        $c = $this->cenarioLojaComHub();
        $this->registrarCompra($c, '10', '500,00');
        $saida = $this->registrarTransferenciaHubParaLoja($c, '10');
        $this->confirmarTransferenciaConforme((int) $saida->transferencia_origem_id, '10');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-HUB-1',
            'id_empresa_origem' => $c['empresa_loja']->id,
            'id_unidade_negocio_estoque' => $c['hub']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300,00'],
            ],
        ])->assertCreated();

        $venda = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->latest('id')
            ->firstOrFail();

        $coLoja = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $c['loja']->id)
            ->where('status_position', true)
            ->firstOrFail();

        $this->assertSame($c['loja']->id, (int) $venda->id_unidade_negocio_faturamento);
        $this->assertSame($c['hub']->id, (int) $venda->id_unidade_negocio_estoque);
        $this->assertSame('1.00', (string) $venda->valor_custo_operacional);
        $this->assertSame((int) $coLoja->id, (int) $venda->id_custo_operacional);
        $this->assertSame('120.00', (string) $venda->valor_custo_saida);
        $this->assertSame('180.00', (string) $venda->resultado_movimentacao);
        $this->assertStringContainsString('Saída física em unidade HUB', (string) $venda->observacao);
        $this->assertStringContainsString($c['loja']->nome, (string) $venda->observacao);
        $this->assertEstoque($c['hub'], $c['fruta'], '0.00', '0.00', '5.00', '50.00', '-20.00');
        $this->assertEstoque($c['loja'], $c['fruta'], '80.00', '8.00', '6.00', '60.00', '480.00');
    }

    public function test_cancelamento_venda_hub_restitui_estoque_com_co_embutido(): void
    {
        $this->seedBase();
        $c = $this->cenarioLojaComHub();
        $this->registrarCompra($c, '10', '500,00');
        $saida = $this->registrarTransferenciaHubParaLoja($c, '10');
        $this->confirmarTransferenciaConforme((int) $saida->transferencia_origem_id, '10');

        $hubAntes = $this->snapshotEstoque($c['hub'], $c['fruta']);
        $lojaAntes = $this->snapshotEstoque($c['loja'], $c['fruta']);

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-HUB-CANCEL',
            'id_empresa_origem' => $c['empresa_loja']->id,
            'id_unidade_negocio_estoque' => $c['hub']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300,00'],
            ],
        ])->assertCreated();

        $venda = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->whereHas('vendaNota', fn ($q) => $q->where('numero_nf', 'NF-HUB-CANCEL'))
            ->firstOrFail();

        $coTotal = round((float) $venda->valor_custo_operacional * (float) $venda->qtd_fruta_kg, 2);
        $custoPm = round((float) $venda->preco_medio_fruta_kg * (float) $venda->qtd_fruta_kg, 2);

        $this->assertSame($c['hub']->id, (int) $venda->id_unidade_negocio_estoque);
        $this->assertSame('120.00', (string) $venda->valor_custo_saida);
        $this->assertSame(round($custoPm + $coTotal, 2), (float) $venda->valor_custo_saida);
        $this->assertGreaterThan(0, $coTotal);
        $this->assertStringContainsString('Saída física em unidade HUB', (string) $venda->observacao);

        $this->assertEstoque($c['hub'], $c['fruta'], '0.00', '0.00', '5.00', '50.00', '-20.00');
        $this->assertEstoque($c['loja'], $c['fruta'], '80.00', '8.00', '6.00', '60.00', '480.00');

        $this->cancelarVendaAdmin($venda);

        $venda->refresh();
        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $venda->status_registro);
        $this->assertSame($hubAntes, $this->snapshotEstoque($c['hub'], $c['fruta']));
        $this->assertSame($lojaAntes, $this->snapshotEstoque($c['loja'], $c['fruta']));
    }

    public function test_realocacao_automatica_ao_vender_do_hub_com_saldo_na_loja(): void
    {
        $this->seedBase();
        $c = $this->cenarioLojaComHub();
        $this->registrarCompra($c, '10', '500,00');

        $saida = $this->registrarTransferenciaHubParaLoja($c, '10');
        $this->confirmarTransferenciaConforme((int) $saida->transferencia_origem_id, '10');

        $this->assertSame('0.00', (string) Estoque::query()->where('id_unidade_negocio', $c['hub']->id)->where('id_fruta', $c['fruta']->id)->value('qtd_fruta_kg'));
        $this->assertSame('100.00', (string) Estoque::query()->where('id_unidade_negocio', $c['loja']->id)->where('id_fruta', $c['fruta']->id)->value('qtd_fruta_kg'));

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-REALOC',
            'id_empresa_origem' => $c['empresa_loja']->id,
            'id_unidade_negocio_estoque' => $c['hub']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '3', 'valor_nf_total' => '450,00'],
            ],
        ])->assertCreated();

        $this->assertEstoque($c['hub'], $c['fruta'], '0.00', '0.00', '5.00', '50.00', '-30.00');
        $this->assertSame('70.00', (string) Estoque::query()->where('id_unidade_negocio', $c['loja']->id)->where('id_fruta', $c['fruta']->id)->value('qtd_fruta_kg'));

        $entradaTransfer = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('7.00', (string) $entradaTransfer->qtd_fruta_um);
    }

    public function test_origem_comercial_nao_aceita_hub_nem_faturamento_separado(): void
    {
        $this->seedBase();

        $origemNormal = $this->cenarioBase();
        $this->registrarCompra($origemNormal, '10', '500,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), array_merge($this->payloadVenda($origemNormal), [
            'id_unidade_negocio_faturamento' => $origemNormal['unidade']->id,
        ]))->assertJsonValidationErrors(['id_unidade_negocio_faturamento']);

        $cHub = $this->cenarioLojaComHub();
        $this->registrarCompra($cHub, '10', '500,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-TESTE',
            'id_empresa_origem' => $cHub['empresa_hub']->id,
            'id_empresa_destino' => $cHub['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $cHub['fruta']->id, 'qtd_fruta_um' => '1', 'valor_nf_total' => '100,00'],
            ],
        ])->assertJsonValidationErrors(['id_empresa_origem']);
    }

    public function test_venda_permite_estoque_negativo_e_preserva_preco_medio(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '1', '50,00');
        $estoqueAntes = Estoque::query()
            ->where('id_unidade_negocio', $c['unidade']->id)
            ->where('id_fruta', $c['fruta']->id)
            ->firstOrFail();

        $venda = $this->registrarVenda($c, '1000.00', '900,00');
        $estoqueDepois = Estoque::query()
            ->where('id_unidade_negocio', $c['unidade']->id)
            ->where('id_fruta', $c['fruta']->id)
            ->firstOrFail();

        $this->assertLessThan(0, (float) $venda->saldo_estoque_fruta_kg);
        $this->assertLessThan(0, (float) $venda->saldo_estoque_fruta_um);
        $this->assertSame((string) $estoqueAntes->preco_medio_kg, (string) $venda->preco_medio_fruta_kg);
        $this->assertSame((string) $estoqueAntes->preco_medio_kg, (string) $estoqueDepois->preco_medio_kg);
        $this->assertSame(number_format((float) $venda->qtd_fruta_kg * (float) $estoqueAntes->preco_medio_kg, 2, '.', ''), (string) $venda->valor_custo_saida);
    }

    public function test_vendas_no_mesmo_frete_rateiam_apenas_ativas_e_atualizam_resultado(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $frete = Frete::factory()->create(['valor' => '100.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]);

        $v1 = $this->registrarVenda($c, '2', '300,00', $frete);
        $v2 = $this->registrarVenda($c, '3', '450,00', $frete);

        $this->assertSame('2.00', (string) $v1->fresh()->valor_frete_kg);
        $this->assertSame('40.00', (string) $v1->fresh()->valor_frete_rateio);
        $this->assertSame('160.00', (string) $v1->fresh()->resultado_movimentacao);
        $this->assertSame('60.00', (string) $v2->fresh()->valor_frete_rateio);
        $this->assertSame('240.00', (string) $v2->fresh()->resultado_movimentacao);
        $this->assertEstoque($c['unidade'], $c['fruta'], '50.00', '5.00', '5.00', '50.00', '250.00');

        $this->cancelarVendaAdmin($v1);

        $this->assertSame(MovimentacaoStatusRegistro::CANCELADO->value, $v1->fresh()->status_registro);
        $this->assertSame('3.33', (string) $v2->fresh()->valor_frete_kg);
        $this->assertSame('100.00', (string) $v2->fresh()->valor_frete_rateio);
        $this->assertSame('200.00', (string) $v2->fresh()->resultado_movimentacao);
    }

    public function test_correcao_de_venda_cria_nova_versao_e_substituida_nao_entra_no_calculo(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $v1 = $this->registrarVenda($c, '2', '300,00');

        $this->actingAs($this->movimentacoesVendasUsuario())->putJson(route('admin.movimentacoes.vendas.update', $v1), [
            'numero_nf' => 'NF-200',
            'id_empresa_origem' => $c['empresa_unidade']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_um' => '3',
            'valor_nf_total' => '480,00',
            'motivo_substituicao' => 'Correção da quantidade.',
        ])->assertOk();

        $v1->refresh();
        $v2 = Movimentacao::query()->findOrFail((int) $v1->substituida_por_id);

        $this->assertSame(MovimentacaoStatusRegistro::SUBSTITUIDO->value, $v1->status_registro);
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $v2->status_registro);
        $this->assertSame(2, (int) $v2->versao);
        $this->assertSame('150.00', (string) $v2->valor_custo_saida);
        $this->assertSame('330.00', (string) $v2->resultado_movimentacao);
        $this->assertSame(1, Movimentacao::query()->vigentesParaCalculo()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)->count());
        $this->assertEstoque($c['unidade'], $c['fruta'], '70.00', '7.00', '5.00', '50.00', '350.00');
    }

    public function test_validacoes_permissoes_cancelamento_e_rollback_transacional(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');

        $this->actingAs($this->userWithoutEmpresaPermissions())->postJson(route('admin.movimentacoes.vendas.store'), $this->payloadVenda($c))
            ->assertForbidden();

        $hub = UnidadeNegocio::factory()->create(['is_hub' => true, 'id_estado' => Estado::ID_PERNAMBUCO]);
        $cenarioHub = $this->cenarioLojaComHub();
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-TESTE',
            'id_empresa_origem' => $cenarioHub['empresa_loja']->id,
            'id_unidade_negocio_estoque' => $hub->id,
            'id_empresa_destino' => $cenarioHub['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $cenarioHub['fruta']->id, 'qtd_fruta_um' => '1', 'valor_nf_total' => '100,00'],
            ],
        ])->assertJsonValidationErrors(['itens.0.id_fruta']);

        $venda = $this->registrarVenda($c, '2', '300,00');
        $this->actingAs($this->userWithoutEmpresaPermissions())->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), [
            'motivo' => 'Sem permissão.',
        ])->assertForbidden();
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), [])
            ->assertJsonValidationErrors(['motivo']);

        $estoqueAntes = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail()->replicate();
        $ultimaAntes = MovimentacaoEstoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->where('status_ultima_posicao', true)->firstOrFail();
        $historicosAntes = MovimentacaoHistorico::query()->count();

        $mock = $this->createMock(ReprocessaSaidasVendaOrigem::class);
        $mock->method('reprocessarSaidasVendaNaUnidadeOrigem')->willThrowException(new RuntimeException('Falha simulada no replay.'));
        $this->app->instance(ReprocessaSaidasVendaOrigem::class, $mock);

        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), ['motivo' => 'Falha simulada.'])
            ->assertServerError();

        $venda->refresh();
        $estoqueDepois = Estoque::query()->where('id_unidade_negocio', $c['unidade']->id)->where('id_fruta', $c['fruta']->id)->firstOrFail();
        $this->assertSame(MovimentacaoStatusRegistro::ATIVO->value, $venda->status_registro);
        $this->assertNull($venda->cancelada_em);
        $this->assertNull($venda->cancelada_por);
        $this->assertNull($venda->motivo_cancelamento);
        $this->assertSame((string) $estoqueAntes->qtd_fruta_kg, (string) $estoqueDepois->qtd_fruta_kg);
        $this->assertSame((string) $estoqueAntes->valor_total_acumulado, (string) $estoqueDepois->valor_total_acumulado);
        $this->assertDatabaseHas('movimentacoes_estoque', ['id' => $ultimaAntes->id, 'status_ultima_posicao' => true]);
        $this->assertSame($historicosAntes, MovimentacaoHistorico::query()->count());
    }

    public function test_origem_comercial_obrigatoria_e_destino_cliente(): void
    {
        $this->seedBase();
        $c = $this->cenarioLojaComHub();
        $this->registrarCompra($c, '5', '250,00');
        $saida = $this->registrarTransferenciaHubParaLoja($c, '5');
        $this->confirmarTransferenciaConforme((int) $saida->transferencia_origem_id, '5');

        $venda = $this->registrarVendaLojaComHub($c, '1', '180,00');

        $this->assertSame($c['empresa_cliente']->id, (int) $venda->id_empresa_destino);
        $this->assertSame($c['loja']->id, (int) $venda->id_unidade_negocio_faturamento);
        $this->assertSame($c['hub']->id, (int) $venda->id_unidade_negocio_estoque);
        $this->assertNotNull($venda->venda_nota_id);
        $this->assertNotNull($venda->vendaNota?->numero_nf);

        $payload = $this->payloadVendaLojaComHub($c);
        unset($payload['id_empresa_destino']);
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), $payload)
            ->assertJsonValidationErrors(['id_empresa_destino']);
    }

    private function seedBase(): void
    {
        $this->seed(EstadoSeeder::class);
        $this->seed(CategoriaMovimentacaoSeeder::class);
        $this->seed(StatusMovimentacaoSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $clienteOverrides
     * @return array<string, mixed>
     */
    private function cenarioBase(bool $origemHub = false, array $clienteOverrides = []): array
    {
        $clienteOverrides = array_replace([
            'desconto_nf' => '0.00',
        ], $clienteOverrides);

        if ($origemHub) {
            return $this->montarCenarioBaseLegacy(true, $clienteOverrides);
        }

        return $this->montarCenarioBase($clienteOverrides);
    }

    /**
     * @param  array<string, mixed>  $clienteOverrides
     * @return array<string, mixed>
     */
    private function cenarioBarbalhaComGalpao(array $clienteOverrides = []): array
    {
        $clienteOverrides = array_replace(['desconto_nf' => '0.00'], $clienteOverrides);
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $cliente = Cliente::factory()->create($clienteOverrides);
        $barbalha = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => '0.00',
            'is_hub' => false,
            'is_galpao_operacional' => false,
            'nome' => 'BARBALHA NF',
        ]);
        $galpao = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => '0.00',
            'is_hub' => false,
            'is_galpao_operacional' => true,
            'nome' => 'GALPAO RECIFE',
            'cpf_cnpj' => null,
        ]);

        HistoricoCOUnNg::query()->whereIn('id_unidade_negocio', [$barbalha->id, $galpao->id])->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $barbalha->id, 'custo_operacional' => '0.00', 'status_position' => true]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $galpao->id, 'custo_operacional' => '0.00', 'status_position' => true]);

        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => 0,
            'entrada_externo' => 0,
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create(['kg_por_unidade_medicao' => 10]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_cliente' => $cliente->registroCorporativo()->firstOrFail(),
            'empresa_barbalha' => $barbalha->registroCorporativo()->firstOrFail(),
            'empresa_galpao' => $galpao->registroCorporativo()->firstOrFail(),
            'barbalha' => $barbalha,
            'galpao' => $galpao,
            'fruta' => $fruta,
            'frete' => Frete::factory()->create(['valor' => '0.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]),
        ];
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarCompraGalpao(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesComprasUsuario())->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $cenario['empresa_fornecedor']->id,
            'id_empresa_destino' => $cenario['empresa_galpao']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $cenario['frete']->id,
        ])->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)->orderByDesc('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $clienteOverrides
     * @return array<string, mixed>
     */
    private function cenarioLojaComHub(array $clienteOverrides = []): array
    {
        $clienteOverrides = array_replace(['desconto_nf' => '0.00'], $clienteOverrides);
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $cliente = Cliente::factory()->create($clienteOverrides);
        $loja = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => '1.00',
            'is_hub' => false,
        ]);
        $hub = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => '0.00',
            'is_hub' => true,
        ]);

        HistoricoCOUnNg::query()->whereIn('id_unidade_negocio', [$loja->id, $hub->id])->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $loja->id, 'custo_operacional' => '1.00', 'status_position' => true]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $hub->id, 'custo_operacional' => '0.00', 'status_position' => true]);

        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => 0,
            'entrada_externo' => 0,
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create(['kg_por_unidade_medicao' => 10]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_cliente' => $cliente->registroCorporativo()->firstOrFail(),
            'empresa_loja' => $loja->registroCorporativo()->firstOrFail(),
            'empresa_hub' => $hub->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $hub->registroCorporativo()->firstOrFail(),
            'loja' => $loja,
            'hub' => $hub,
            'unidade' => $hub,
            'fruta' => $fruta,
            'frete' => Frete::factory()->create(['valor' => '0.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]),
        ];
    }

    /**
     * @param  array<string, mixed>  $clienteOverrides
     * @return array<string, mixed>
     */
    private function montarCenarioBase(array $clienteOverrides): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $cliente = Cliente::factory()->create($clienteOverrides);
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
            'is_hub' => false,
        ]);

        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidade->id, 'custo_operacional' => 0, 'status_position' => true]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_cliente' => $cliente->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $unidade->registroCorporativo()->firstOrFail(),
            'unidade' => $unidade,
            'fruta' => Fruta::factory()->comIcmsCeara([
                'entrada_nacional' => 0,
                'entrada_externo' => 0,
                'entrada_um' => FrutaUmIcms::KG->value,
            ])->create([
                'kg_por_unidade_medicao' => 10,
            ]),
            'frete' => Frete::factory()->create(['valor' => '0.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]),
        ];
    }

    /**
     * @param  array<string, mixed>  $clienteOverrides
     * @return array<string, mixed>
     */
    private function montarCenarioBaseLegacy(bool $origemHub, array $clienteOverrides): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $cliente = Cliente::factory()->create($clienteOverrides);
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
            'is_hub' => $origemHub,
        ]);
        $unidadeFaturamento = UnidadeNegocio::factory()->create([
            'possui_estoque' => false,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
            'is_hub' => false,
        ]);

        HistoricoCOUnNg::query()->whereIn('id_unidade_negocio', [$unidade->id, $unidadeFaturamento->id])->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidade->id, 'custo_operacional' => 0, 'status_position' => true]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidadeFaturamento->id, 'custo_operacional' => 0, 'status_position' => true]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_cliente' => $cliente->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $unidade->registroCorporativo()->firstOrFail(),
            'unidade' => $unidade,
            'unidade_faturamento' => $unidadeFaturamento,
            'fruta' => Fruta::factory()->comIcmsCeara([
                'entrada_nacional' => 0,
                'entrada_externo' => 0,
                'entrada_um' => FrutaUmIcms::KG->value,
            ])->create([
                'kg_por_unidade_medicao' => 10,
            ]),
            'frete' => Frete::factory()->create(['valor' => '0.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]),
        ];
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarCompra(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesComprasUsuario())->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $cenario['empresa_fornecedor']->id,
            'id_empresa_destino' => $cenario['empresa_unidade']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $cenario['frete']->id,
        ])->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)->orderByDesc('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarVenda(array $cenario, string $qtdUm, string $valorNfTotal, ?Frete $frete = null): Movimentacao
    {
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), $this->payloadVenda($cenario, $qtdUm, $valorNfTotal, $frete))->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     * @return array<string, mixed>
     */
    private function payloadVenda(array $cenario, string $qtdUm = '1', string $valorNfTotal = '100,00', ?Frete $frete = null): array
    {
        return [
            'numero_nf' => 'NF-TESTE',
            'id_empresa_origem' => $cenario['empresa_unidade']->id,
            'id_empresa_destino' => $cenario['empresa_cliente']->id,
            'id_frete' => $frete?->id,
            'itens' => [
                ['id_fruta' => $cenario['fruta']->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarVendaLojaComHub(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(
            route('admin.movimentacoes.vendas.store'),
            $this->payloadVendaLojaComHub($cenario, $qtdUm, $valorNfTotal),
        )->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     * @return array<string, mixed>
     */
    private function payloadVendaLojaComHub(array $cenario, string $qtdUm = '1', string $valorNfTotal = '100,00'): array
    {
        return [
            'numero_nf' => 'NF-TESTE',
            'id_empresa_origem' => $cenario['empresa_loja']->id,
            'id_unidade_negocio_estoque' => $cenario['hub']->id,
            'id_empresa_destino' => $cenario['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $cenario['fruta']->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarTransferenciaHubParaLoja(array $cenario, string $qtdUm): Movimentacao
    {
        $this->actingAs($this->movimentacoesTransferenciasUsuario())->postJson(route('admin.movimentacoes.transferencias.store'), [
            'id_empresa_origem' => $cenario['empresa_hub']->id,
            'id_empresa_destino' => $cenario['empresa_loja']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
        ])->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    private function confirmarTransferenciaConforme(int $transferenciaOrigemId, string $qtdRecebidaUm): void
    {
        // Transferências são efetivadas na criação (ADR-0065).
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarVendaProducao(
        array $cenario,
        string $qtdUm,
        string $valorNfTotal,
        ?UnidadeNegocio $hub,
        bool $aplicarCo,
    ): Movimentacao {
        $payload = array_merge($this->payloadVenda($cenario, $qtdUm, $valorNfTotal), [
            'aplicar_custo_operacional_hub' => $aplicarCo ? '1' : '0',
            'id_unidade_negocio_hub_custo' => $aplicarCo && $hub !== null ? $hub->id : null,
        ]);

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(
            route('admin.movimentacoes.vendas.store'),
            $payload,
        )->assertCreated();

        return Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    public function test_corrige_venda_hub_legada_sem_co_embutido_no_custo_saida(): void
    {
        $this->seedBase();
        $c = $this->cenarioLojaComHub();
        $this->registrarCompra($c, '10', '500,00');
        $saida = $this->registrarTransferenciaHubParaLoja($c, '10');
        $this->confirmarTransferenciaConforme((int) $saida->transferencia_origem_id, '10');

        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-HUB-LEGADO',
            'id_empresa_origem' => $c['empresa_loja']->id,
            'id_unidade_negocio_estoque' => $c['hub']->id,
            'id_empresa_destino' => $c['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'qtd_fruta_um' => '2', 'valor_nf_total' => '300,00'],
            ],
        ])->assertCreated();

        $venda = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->whereHas('vendaNota', fn ($q) => $q->where('numero_nf', 'NF-HUB-LEGADO'))
            ->firstOrFail();

        $coErrado = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $c['hub']->id)
            ->where('status_position', true)
            ->firstOrFail();

        $venda->forceFill([
            'valor_custo_saida' => '100.00',
            'valor_total_movimentacao' => '100.00',
            'observacao' => null,
            'id_custo_operacional' => $coErrado->id,
            'valor_custo_operacional' => '2.00',
            'resultado_movimentacao' => '200.00',
        ])->saveQuietly();

        $corrigidas = app(\App\Services\Movimentacoes\CorrigirCustosVendaSaidaHubService::class)
            ->corrigirNota('NF-HUB-LEGADO');

        $this->assertSame(1, $corrigidas);
        $venda->refresh();
        $this->assertSame('120.00', (string) $venda->valor_custo_saida);
        $this->assertStringContainsString('Saída física em unidade HUB', (string) $venda->observacao);
        $this->assertSame('180.00', (string) $venda->resultado_movimentacao);
    }

    private function cancelarVendaAdmin(Movimentacao $venda): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::MOVIMENTACOES_VENDAS_CANCELAR_ADMIN]))
            ->postJson(route('admin.movimentacoes.vendas.cancelar-admin', $venda), ['motivo' => 'Cancelamento administrativo de venda.'])
            ->assertOk();
    }

    private function assertEstoque(UnidadeNegocio $unidade, Fruta $fruta, string $kg, string $um, string $precoKg, string $precoUm, string $valor): void
    {
        $estoque = Estoque::query()->where('id_unidade_negocio', $unidade->id)->where('id_fruta', $fruta->id)->firstOrFail();

        $this->assertSame($kg, (string) $estoque->qtd_fruta_kg);
        $this->assertSame($um, (string) $estoque->qtd_fruta_um);
        $this->assertSame($precoKg, (string) $estoque->preco_medio_kg);
        $this->assertSame($precoUm, (string) $estoque->preco_medio_um);
        $this->assertSame($valor, (string) $estoque->valor_total_acumulado);
    }

    /**
     * @return array{
     *     qtd_fruta_kg: string,
     *     qtd_fruta_um: string,
     *     preco_medio_kg: string,
     *     preco_medio_um: string,
     *     valor_total_acumulado: string,
     * }
     */
    private function snapshotEstoque(UnidadeNegocio $unidade, Fruta $fruta): array
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        return [
            'qtd_fruta_kg' => (string) $estoque->qtd_fruta_kg,
            'qtd_fruta_um' => (string) $estoque->qtd_fruta_um,
            'preco_medio_kg' => (string) $estoque->preco_medio_kg,
            'preco_medio_um' => (string) $estoque->preco_medio_um,
            'valor_total_acumulado' => (string) $estoque->valor_total_acumulado,
        ];
    }
}
