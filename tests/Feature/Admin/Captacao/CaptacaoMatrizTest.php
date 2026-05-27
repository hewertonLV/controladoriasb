<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;
use App\Models\Cliente;
use App\Services\Captacao\ClienteFrutaVinculoService;
use App\Services\Captacao\PedidoService;

class CaptacaoMatrizTest extends CaptacaoTestCase
{
    public function test_matriz_exibe_abas_quantidade_e_rotas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '12',
            'preco_venda' => '5.50',
            'version' => 1,
        ]);

        $frutaId = $c['fruta']->id;

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertOk()
            ->assertSee('Captação — '.$c['galpao']->nome, false)
            ->assertDontSee('Matriz —', false)
            ->assertSee('select-nova-loja', false)
            ->assertSee('Rotas', false)
            ->assertSee('Por rota', false)
            ->assertSee('matriz-tab-quantidade', false)
            ->assertSee('captacao-matriz-rotas', false)
            ->assertSee('matriz-rota-select', false)
            ->assertSee('Total', false)
            ->assertDontSee('Linha do tempo do lote', false)
            ->assertSee('captacao-lote-timeline', false)
            ->assertSee('Status atual:', false)
            ->assertSee('Faturamento:', false)
            ->assertSee('matriz-sync-badge', false)
            ->assertSee('Romaneio', false)
            ->assertSee('Finalizar captação (faturamento)', false)
            ->assertViewHas('totaisPorFruta', function (array $totais) use ($frutaId): bool {
                return ($totais[$frutaId] ?? 0) === 12.0;
            });

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rotas']))
            ->assertOk()
            ->assertSee('Qtd (UM)', false)
            ->assertSee($c['fruta']->nome, false);
    }

    public function test_matriz_permite_rota_e_ordem_quando_loja_concluida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $rotaB = CaptacaoRota::query()->create([
            'id_captacao_carteira' => $c['carteira']->id,
            'nome' => 'Rota B',
            'ativo' => true,
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
                'id_captacao_rota' => $c['rota']->id,
            ])
            ->assertOk()
            ->assertJsonPath('id_captacao_rota', $c['rota']->id);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
                'id_captacao_rota' => $rotaB->id,
            ])
            ->assertOk()
            ->assertJsonPath('id_captacao_rota', $rotaB->id);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
                'ordem_carregamento' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('pedidos_rota.0.ordem_carregamento', 1);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rotas']))
            ->assertOk()
            ->assertSee('matriz-rota-select', false)
            ->assertDontSee('matriz-rota-select" disabled', false);

        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $rotaB->id,
            'ordem_carregamento' => 1,
            'captacao_concluida' => true,
        ]);
    }

    public function test_matriz_permite_vinculo_rota_apos_finalizar_captacao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
        ])->assertOk();

        $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 8,
        ])->assertUnprocessable();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
                'id_captacao_rota' => $c['rota']->id,
            ])
            ->assertOk()
            ->assertJsonPath('id_captacao_rota', $c['rota']->id);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rotas']))
            ->assertOk()
            ->assertSee('matriz-rota-select', false);
    }

    public function test_matriz_bloqueia_edicao_de_celula_quando_loja_concluida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertOk()
            ->assertSee('matriz-row-loja-concluida', false)
            ->assertSee('disabled', false);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 8,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantidade');

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 5,
                'preco_venda' => '9.90',
            ])
            ->assertOk()
            ->assertJsonPath('item.preco_venda', '9.9000');
    }

    public function test_matriz_permite_alterar_preco_apos_finalizar_captacao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => '10.00',
        ])->assertOk();

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaFinalizada]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 5,
                'preco_venda' => '11.50',
            ])
            ->assertOk()
            ->assertJsonPath('item.preco_venda', '11.5000');
    }

    public function test_matriz_permite_alterar_preco_apos_iniciar_transferencia_cigan(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => '10.00',
        ])->assertOk();

        $lote->update(['status' => CaptacaoLoteStatus::AguardandoVinculoFrete]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 5,
                'preco_venda' => '13.25',
            ])
            ->assertOk()
            ->assertJsonPath('item.preco_venda', '13.2500');

        $this->assertDatabaseHas('pedido_itens', [
            'id_fruta' => $c['fruta']->id,
            'preco_venda' => '13.2500',
        ]);
    }

    public function test_matriz_bloqueia_preco_quando_faturamento_cigan_iniciado(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
        ])->assertOk();

        $lote->update(['status' => CaptacaoLoteStatus::FaturamentoCiganIniciado]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 5,
                'preco_venda' => '12.00',
            ])
            ->assertUnprocessable();
    }

    public function test_matriz_salva_numero_pedido(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.numero-pedido', [$lote, $c['cliente']]), [
                'numero_pedido' => 'PED-12345',
            ])
            ->assertOk()
            ->assertJsonPath('numero_pedido', 'PED-12345');

        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'numero_pedido' => 'PED-12345',
        ]);
    }

    public function test_matriz_vincula_rota_por_loja(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 3,
        ])->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
                'id_captacao_rota' => $c['rota']->id,
            ])
            ->assertOk()
            ->assertJsonPath('id_captacao_rota', $c['rota']->id);

        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
        ]);
    }

    public function test_estado_matriz_inclui_conclusao_rota_e_linhas_rotas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.numero-pedido', [$lote, $c['cliente']]), [
            'numero_pedido' => '998877',
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 2,
            'preco_venda' => 4.5,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
            'id_captacao_rota' => $c['rota']->id,
        ])->assertOk();

        $estado = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->json();

        $clienteId = (string) $c['cliente']->id;
        $this->assertSame('998877', $estado['pedidos'][$clienteId]['numero_pedido']);
        $this->assertSame($c['rota']->id, $estado['pedidos'][$clienteId]['id_captacao_rota']);
        $this->assertNotEmpty($estado['linhas_rotas']);
        $this->assertSame($c['cliente']->id, $estado['linhas_rotas'][0]['id_cliente']);
        $this->assertSame($c['rota']->id, $estado['rotas'][0]['id']);
        $this->assertSame('Rota Teste', $estado['rotas'][0]['nome']);
    }

    public function test_matriz_rotas_aba_avisa_quando_carteira_sem_rotas_cadastradas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 1,
        ])->assertOk();

        $c['rota']->delete();

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rotas']))
            ->assertOk()
            ->assertSee('Nenhuma rota ativa cadastrada para esta carteira', false)
            ->assertSee('Nenhuma rota nesta carteira', false)
            ->assertSee($c['carteira']->nome, false);
    }

    public function test_matriz_aba_por_rota_exibe_ordem_carregamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 4,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
            'id_captacao_rota' => $c['rota']->id,
        ])->assertOk();

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'por-rota']))
            ->assertOk()
            ->assertSee('captacao-matriz-ordem', false)
            ->assertSee('Ordem de Carregamento', false)
            ->assertSee('matriz-ordem-select', false)
            ->assertSee('matriz-rota-motorista', false)
            ->assertSee('matriz-rota-veiculo', false)
            ->assertSee($c['rota']->nome, false)
            ->assertSee($c['fruta']->nome, false);
    }

    public function test_matriz_salva_nome_motorista_na_aba_por_rota(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 3,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
            'id_captacao_rota' => $c['rota']->id,
        ])->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote, $c['rota']]), [
                'nome_motorista' => 'João Motorista',
            ])
            ->assertOk()
            ->assertJsonPath('nome_motorista', 'João Motorista');

        $this->assertDatabaseHas('captacao_rotas', [
            'id' => $c['rota']->id,
            'nome_motorista' => 'João Motorista',
        ]);

        $estado = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->json();

        $this->assertSame('João Motorista', $estado['grupos_ordem_carregamento'][0]['motorista_nome']);
    }

    public function test_matriz_salva_veiculo_na_aba_por_rota(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'nome' => 'CAMINHAO TESTE',
            'status' => 'ATIVO',
        ]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 3,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
            'id_captacao_rota' => $c['rota']->id,
        ])->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $c['rota']]), [
                'id_veiculo' => $veiculo->id,
            ])
            ->assertOk()
            ->assertJsonPath('id_veiculo', $veiculo->id)
            ->assertJsonPath('veiculo_rotulo', 'CAMINHAO TESTE (SBS '.$veiculo->id_sbs.')');

        $this->assertDatabaseHas('captacao_rotas', [
            'id' => $c['rota']->id,
            'id_veiculo' => $veiculo->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'por-rota']))
            ->assertOk()
            ->assertSee('CAMINHAO TESTE (SBS '.$veiculo->id_sbs.')', false);

        $estado = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->json();

        $this->assertSame($veiculo->id, $estado['grupos_ordem_carregamento'][0]['id_veiculo']);
    }

    public function test_matriz_veiculo_nao_repete_entre_rotas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $rotaB = CaptacaoRota::query()->create([
            'id_captacao_carteira' => $c['carteira']->id,
            'nome' => 'Rota B Teste',
            'ativo' => true,
        ]);

        $veiculoA = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'nome' => 'CAMINHAO A',
            'status' => 'ATIVO',
        ]);
        $veiculoB = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'nome' => 'CAMINHAO B',
            'status' => 'ATIVO',
        ]);

        $clienteB = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'CLIENTE B VEICULO',
            'fantasia' => null,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);

        $pedidoService = app(PedidoService::class);
        $pedidoService->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);
        $pedidoService->adicionarLojaNaMatriz($lote, $clienteB, \App\Enums\PedidoOrigem::Web, $user);

        foreach ([[$c['cliente'], $c['rota']], [$clienteB, $rotaB]] as [$cliente, $rota]) {
            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $cliente->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 3,
            ])->assertOk();

            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $cliente]), [
                'id_captacao_rota' => $rota->id,
            ])->assertOk();
        }

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $c['rota']]), [
                'id_veiculo' => $veiculoA->id,
            ])
            ->assertOk();

        $html = $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'por-rota']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('CAMINHAO A (SBS '.$veiculoA->id_sbs.')', $html);
        $this->assertStringContainsString('CAMINHAO B (SBS '.$veiculoB->id_sbs.')', $html);

        preg_match_all(
            '/<select[^>]*matriz-rota-veiculo[^>]*data-rota="'.$rotaB->id.'"[^>]*>(.*?)<\/select>/s',
            $html,
            $matches,
        );
        $this->assertNotEmpty($matches[1]);
        $this->assertStringNotContainsString('CAMINHAO A (SBS '.$veiculoA->id_sbs.')', $matches[1][0]);
        $this->assertStringContainsString('CAMINHAO B (SBS '.$veiculoB->id_sbs.')', $matches[1][0]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $rotaB]), [
                'id_veiculo' => $veiculoA->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_veiculo']);
    }

    public function test_matriz_atualiza_ordem_carregamento_e_reordena_lojas_da_rota(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $clienteB = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'CLIENTE B CAPTACAO',
            'fantasia' => null,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);

        $pedidoService = app(PedidoService::class);
        $pedidoService->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);
        $pedidoService->adicionarLojaNaMatriz($lote, $clienteB, \App\Enums\PedidoOrigem::Web, $user);

        foreach ([$c['cliente'], $clienteB] as $cliente) {
            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $cliente->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 2,
            ])->assertOk();

            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $cliente]), [
                'id_captacao_rota' => $c['rota']->id,
            ])->assertOk();
        }

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $clienteB]), [
                'ordem_carregamento' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('pedidos_rota.0.id_cliente', $clienteB->id)
            ->assertJsonPath('pedidos_rota.0.ordem_carregamento', 1);

        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $clienteB->id,
            'ordem_carregamento' => 1,
        ]);
        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'ordem_carregamento' => 2,
        ]);

        $estado = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($estado['grupos_ordem_carregamento']);
        $this->assertSame($clienteB->id, $estado['grupos_ordem_carregamento'][0]['lojas'][0]['id_cliente']);
    }

    public function test_matriz_reordena_lojas_ao_alterar_ordem_carregamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $clienteB = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'CLIENTE B ORDEM',
            'fantasia' => 'LOJA B',
        ]);
        $clienteC = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'CLIENTE C ORDEM',
            'fantasia' => 'LOJA C',
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteC, [$c['fruta']->id]);

        $pedidoService = app(PedidoService::class);
        foreach ([$c['cliente'], $clienteB, $clienteC] as $cliente) {
            $pedidoService->adicionarLojaNaMatriz($lote, $cliente, \App\Enums\PedidoOrigem::Web, $user);

            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $cliente->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 1,
            ])->assertOk();

            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $cliente]), [
                'id_captacao_rota' => $c['rota']->id,
            ])->assertOk();
        }

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
            'ordem_carregamento' => 1,
        ])->assertOk();
        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $clienteB]), [
            'ordem_carregamento' => 2,
        ])->assertOk();
        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $clienteC]), [
            'ordem_carregamento' => 3,
        ])->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $clienteC]), [
                'ordem_carregamento' => 1,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $clienteC->id,
            'ordem_carregamento' => 1,
        ]);
        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'ordem_carregamento' => 2,
        ]);
        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $clienteB->id,
            'ordem_carregamento' => 3,
        ]);

        $estado = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->json();

        $lojas = $estado['grupos_ordem_carregamento'][0]['lojas'];
        $this->assertSame($clienteC->id, $lojas[0]['id_cliente']);
        $this->assertSame($c['cliente']->id, $lojas[1]['id_cliente']);
        $this->assertSame($clienteB->id, $lojas[2]['id_cliente']);
    }

    public function test_trocar_rota_limpa_ordem_carregamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 1,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
            'id_captacao_rota' => $c['rota']->id,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
            'ordem_carregamento' => 1,
        ])->assertOk();

        $rotaB = CaptacaoRota::query()->create([
            'id_captacao_carteira' => $c['carteira']->id,
            'nome' => 'Rota B',
            'ativo' => true,
        ]);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
            'id_captacao_rota' => $rotaB->id,
        ])->assertOk()
            ->assertJsonPath('ordem_carregamento', null);

        $this->assertDatabaseHas('pedidos', [
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'ordem_carregamento' => null,
        ]);
    }
}
