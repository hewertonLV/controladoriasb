<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\Roles;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Cliente;
use App\Enums\FreteStatusSituacao;
use App\Services\Captacao\ClienteFrutaVinculoService;
use App\Services\Captacao\GerarVendasCaptacaoLoteService;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Frete;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\VendaNota;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class CaptacaoIntegracaoMovimentacoesTest extends CaptacaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCaptacaoMovimentacao();
    }

    public function test_validar_transferencias_cria_transferencia_hub_para_galpao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 10);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote))
            ->assertRedirect();

        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        Storage::fake('local');

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
                'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
            ])
            ->assertRedirect();

        $this->concluirSaidaEstoqueFisicoPipeline($user, $lote->fresh());

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
            'id_fruta' => $c['fruta']->id,
        ]);

        $vinculo = CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->first();

        $this->assertNotNull($vinculo?->transferencia_origem_id);

        $saida = Movimentacao::query()
            ->where('transferencia_origem_id', $vinculo->transferencia_origem_id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->first();

        $this->assertNotNull($saida);
        $empresaHub = $hub->registroCorporativo()->firstOrFail();
        $this->assertSame($empresaHub->id, (int) $saida->id_empresa_origem);
        $this->assertSame($c['galpao']->registroCorporativo()->firstOrFail()->id, (int) $saida->id_empresa_destino);
    }

    public function test_upload_nf_bloqueia_quando_hub_sem_estoque_suficiente(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '50.00', '5.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 10);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        Storage::fake('local');

        $upload = $this->actingAs($user)
            ->from(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
                'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
            ])
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->assertSessionHas('nf_transferencia_estoque_hub_insuficiente')
            ->assertSessionHas('nf_transferencia_estoque_hub_insuficiente.frutas');

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::TransferenciaCiganIniciada, $lote->status);
        $this->assertFalse($lote->possuiNfTransferencia());

        $faltas = $upload->getSession()->get('nf_transferencia_estoque_hub_insuficiente');
        $this->assertIsArray($faltas);
        $this->assertSame($c['fruta']->nome, $faltas['frutas'][0]['fruta_nome']);
        $this->assertSame('5,00', $faltas['frutas'][0]['estoque_um']);
        $this->assertSame('5,00', $faltas['frutas'][0]['falta_um']);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->assertOk()
            ->assertSee('id="modal-nf-estoque-hub-insuficiente"', false)
            ->assertSee('Estoque insuficiente', false)
            ->assertSee('btn-toggle-nf-estoque-hub-lista', false)
            ->assertSee($c['fruta']->nome, false)
            ->assertSee('5,00', false);
    }

    public function test_matriz_estado_inclui_saida_fisica_venda_por_loja(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $lote = $this->criarLoteComPedido($c, 3);
        $lote->update([
            'status' => CaptacaoLoteStatus::SaidaEstoqueFisico,
            'id_unidade_negocio_hub_origem' => $hub->id,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $pedido = $lote->fresh()->pedidos()->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $estado = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->json();

        $clienteId = (string) $c['cliente']->id;
        $this->assertSame($hub->id, $estado['pedidos'][$clienteId]['id_unidade_negocio_saida_venda']);
    }

    public function test_upload_nf_preenche_saida_hub_quando_cliente_padrao_hub(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $c['cliente']->update(['id_unidade_negocio_saida_fisico_padrao' => $hub->id]);
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 10);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $pedido = $lote->fresh()->pedidos()->firstOrFail();
        $this->assertNull($pedido->id_unidade_negocio_saida_venda);

        Storage::fake('local');
        $this->actingAs($user)->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
            'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
        ])->assertRedirect();

        $pedido->refresh();
        $this->assertSame($hub->id, $pedido->id_unidade_negocio_saida_venda);
    }

    public function test_matriz_estado_usa_padrao_cliente_quando_saida_venda_null(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $c['cliente']->update(['id_unidade_negocio_saida_fisico_padrao' => $hub->id]);
        $lote = $this->criarLoteComPedido($c, 3);
        $lote->update([
            'status' => CaptacaoLoteStatus::SaidaEstoqueFisico,
            'id_unidade_negocio_hub_origem' => $hub->id,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $pedido = $lote->fresh()->pedidos()->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => null]);

        $clienteId = (string) $c['cliente']->id;
        $estado = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->json();

        $this->assertSame($hub->id, $estado['pedidos'][$clienteId]['id_unidade_negocio_saida_venda']);
    }

    public function test_concluir_saida_fisico_nao_transfere_quando_loja_vende_direto_do_hub(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 10);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        Storage::fake('local');
        $this->actingAs($user)->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
            'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
        ]);

        $lote->refresh();
        $pedido = $lote->pedidos()->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->concluirSaidaEstoqueFisicoPipeline($user, $lote->fresh());

        $this->assertDatabaseMissing('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
        ]);

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::AguardandoVinculoFrete, $lote->status);
    }

    public function test_upload_nf_usa_hub_selecionado_na_aba_cigan_e_nao_origem_do_pedido(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hubSelecionado = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $hubOutro = $this->criarHubComEstoque($c['fruta'], '50.00', '5.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 10);

        $pedido = $lote->pedidos()->firstOrFail();
        $item = $pedido->itens()->firstOrFail();
        $item->update(['id_unidade_origem_fisica' => $hubOutro->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hubSelecionado->id, $hubOutro->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hubSelecionado->id]);

        Storage::fake('local');

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
                'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
            ])
            ->assertRedirect();

        $this->concluirSaidaEstoqueFisicoPipeline($user, $lote->fresh());

        $vinculo = CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->first();

        $saida = Movimentacao::query()
            ->where('transferencia_origem_id', $vinculo->transferencia_origem_id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->first();

        $this->assertSame(
            $hubSelecionado->registroCorporativo()->firstOrFail()->id,
            (int) $saida->id_empresa_origem,
        );
        $this->assertNotSame(
            $hubOutro->registroCorporativo()->firstOrFail()->id,
            (int) $saida->id_empresa_origem,
        );
    }

    public function test_finalizar_vendas_cria_nota_por_cliente(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 5, '12.50');

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);
        Storage::fake('local');
        $this->actingAs($user)->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
            'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
        ]);
        $this->concluirSaidaEstoqueFisicoPipeline($user, $lote->fresh());
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.concluir-frete', $lote));
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote));

        $this->enviarNfVendaPipeline($user, $lote);

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
        ]);

        $this->assertGreaterThan(0, VendaNota::query()->count());
    }

    public function test_venda_captacao_saida_hub_embuti_co_faturamento_no_custo_saida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $c['faturamento']->id)
            ->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'custo_operacional' => '2.00',
            'status_position' => true,
        ]);
        $c['faturamento']->forceFill(['custo_operacional' => '2.00'])->save();

        $lote = $this->criarLoteComPedido($c, 5, '12.50');

        $pedido = Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $c['cliente']->id)
            ->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);
        Storage::fake('local');
        $this->actingAs($user)->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
            'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
        ]);
        $this->concluirSaidaEstoqueFisicoPipeline($user, $lote->fresh());
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.concluir-frete', $lote));
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote));
        $this->enviarNfVendaPipeline($user, $lote->fresh());

        $numeroNf = sprintf('CAP-%s-%d-%d', $lote->data_referencia->format('Ymd'), $lote->id, $c['cliente']->id);
        $nota = VendaNota::query()->where('numero_nf', $numeroNf)->firstOrFail();

        $venda = Movimentacao::query()
            ->where('venda_nota_id', $nota->id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->firstOrFail();

        $this->assertSame($hub->id, (int) $venda->id_unidade_negocio_estoque);
        $this->assertSame($c['faturamento']->id, (int) $venda->id_unidade_negocio_faturamento);
        $this->assertSame('2.00', (string) $venda->valor_custo_operacional);
        $this->assertSame('350.00', (string) $venda->valor_custo_saida);
        $this->assertStringContainsString('Saída física em unidade HUB', (string) $venda->observacao);
        $this->assertStringContainsString($c['faturamento']->nome, (string) $venda->observacao);

        $estoqueHub = Estoque::query()
            ->where('id_unidade_negocio', $hub->id)
            ->where('id_fruta', $c['fruta']->id)
            ->firstOrFail();
        $this->assertSame('5.00', (string) $estoqueHub->preco_medio_kg);

        $this->assertFalse(
            Estoque::query()
                ->where('id_unidade_negocio', $c['galpao']->id)
                ->where('id_fruta', $c['fruta']->id)
                ->exists(),
        );
    }

    public function test_matriz_frete_vendas_lista_loja_e_vincula_frete(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 5, '12.50');

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);
        Storage::fake('local');
        $this->actingAs($user)->post(route('admin.captacao.lotes.nf-transferencia-cigan.upload', $lote), [
            'arquivo_nf_transferencia' => UploadedFile::fake()->create('nf.xml', 50, 'application/xml'),
        ]);
        $this->concluirSaidaEstoqueFisicoPipeline($user, $lote->fresh());
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.concluir-frete', $lote));
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote));
        $this->enviarNfVendaPipeline($user, $lote);
        $this->concluirRotasECarregamentoPipeline($user, $lote->fresh(), $c);

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VincularFreteVenda, $lote->status);

        $lojaNome = $c['cliente']->fantasia ?: $c['cliente']->razao_social;

        $frete = Frete::factory()->create([
            'valor' => '100.00',
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.fretes.venda-loja', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_frete' => $frete->id,
            ])
            ->assertOk()
            ->assertJsonPath('status_label', 'Vinculado')
            ->assertJsonPath('id_frete', $frete->id);

        $numeroNf = sprintf('CAP-%s-%d-%d', $lote->data_referencia->format('Ymd'), $lote->id, $c['cliente']->id);
        $nota = VendaNota::query()->where('numero_nf', $numeroNf)->firstOrFail();

        $this->assertTrue(
            Movimentacao::query()
                ->where('venda_nota_id', $nota->id)
                ->where('id_frete', $frete->id)
                ->exists(),
        );

        $this->concluirFreteVendaPipeline($user, $lote->fresh());
        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VendasFinalizadas, $lote->status);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'frete-vendas']))
            ->assertOk()
            ->assertSee('Frete Vendas', false)
            ->assertSee($lojaNome, false)
            ->assertSee($c['fruta']->nome, false)
            ->assertSee('bloqueado', false);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.fretes.venda-loja', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_frete' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $admin = $this->captacaoManager();
        $admin->assignRole(Role::findOrCreate(Roles::ADMINISTRADOR->value, 'web'));
        $admin->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($admin)
            ->postJson(route('admin.captacao.lotes.fretes.venda-loja', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_frete' => '',
            ])
            ->assertOk()
            ->assertJsonPath('status_label', 'Sem Frete')
            ->assertJsonPath('id_frete', null);

        $this->assertFalse(
            Movimentacao::query()
                ->where('venda_nota_id', $nota->id)
                ->whereNotNull('id_frete')
                ->exists(),
        );
    }

    public function test_gerar_vendas_gera_lojas_pendentes_apos_primeira_execucao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $cliente2 = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'LOJA SEGUNDA CAPTACAO',
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($cliente2, [$c['fruta']->id]);

        $lote = $this->criarLoteComPedido($c, 5, '12.50');
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $gerador = app(GerarVendasCaptacaoLoteService::class);
        $gerador->executar($lote->fresh(), $user);

        $this->assertSame(1, CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->count());

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $cliente2->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [
                [
                    'id_fruta' => $c['fruta']->id,
                    'quantidade' => 3,
                    'preco_venda' => '10.00',
                ],
            ],
        ]);

        $pedido2 = Pedido::query()->firstOrCreate(
            [
                'id_captacao_lote' => $lote->id,
                'id_cliente' => $cliente2->id,
            ],
            [
                'id_captacao_rota' => $c['rota']->id,
                'id_unidade_negocio_saida_venda' => $c['galpao']->id,
            ],
        );
        $pedido2->update(['id_unidade_negocio_saida_venda' => $c['galpao']->id]);
        PedidoItem::query()->updateOrCreate(
            [
                'id_pedido' => $pedido2->id,
                'id_fruta' => $c['fruta']->id,
            ],
            [
                'quantidade' => 3,
                'preco_venda' => '10.00',
            ],
        );

        $gerador->executar($lote->fresh(), $user);

        $this->assertSame(2, CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->count());

        $numeroNf2 = sprintf('CAP-%s-%d-%d', $lote->data_referencia->format('Ymd'), $lote->id, $cliente2->id);
        $this->assertTrue(VendaNota::query()->where('numero_nf', $numeroNf2)->exists());
    }

    public function test_matriz_estado_e_incremento_celula(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
            'id_cliente' => $c['cliente']->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->assertJsonStructure(['version', 'celulas']);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'incremento' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('item.quantidade', '2.000');

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 2,
                'preco_venda' => '15.75',
            ])
            ->assertOk()
            ->assertJsonPath('item.preco_venda', '15.7500');
    }

    public function test_telas_romaneio_manual_e_frete(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::AguardandoVinculoFrete,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)
            ->get(route('admin.captacao.romaneio-manual.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.fretes.index', $lote))
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'frete-hub']));

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'frete-hub']))
            ->assertOk()
            ->assertSee('Frete HUB x CD', false)
            ->assertSee('Transferências do lote', false);
    }

    public function test_api_contrato_publico(): void
    {
        $this->getJson(route('api.v1.captacao.contrato'))
            ->assertOk()
            ->assertJsonPath('adr', 'ADR-0081');
    }

}
