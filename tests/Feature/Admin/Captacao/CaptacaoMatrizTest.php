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
            ->assertDontSee('matriz-nav-por-rota', false)
            ->assertSee('matriz-tab-quantidade', false)
            ->assertSee('captacao-matriz-rotas', false)
            ->assertSee('matriz-rota-select', false)
            ->assertSee('Total', false)
            ->assertDontSee('Linha do tempo do lote', false)
            ->assertDontSee('captacao-lote-timeline', false)
            ->assertDontSee('Status atual:', false)
            ->assertDontSee('Faturamento:', false)
            ->assertSee('matriz-sync-badge', false)
            ->assertSee('Concluir captação', false)
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
        $this->assertSame($c['galpao']->nome, $estado['linhas_rotas'][0]['saida_fisica_nome']);
        $this->assertSame($c['rota']->id, $estado['rotas'][0]['id']);
        $this->assertSame('Rota Teste', $estado['rotas'][0]['nome']);
        $this->assertArrayHasKey('conclusao_captacao_lote', $estado);
        $this->assertFalse($estado['conclusao_captacao_lote']['pode']);
        $this->assertNotEmpty($estado['conclusao_captacao_lote']['pendencias']);
        $this->assertSame('CAPTACAO_EM_ANDAMENTO', $estado['conclusao_captacao_lote']['lote_status']);
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
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rota-'.$c['rota']->id]))
            ->assertOk()
            ->assertSee('matriz-ordem-rota-table', false)
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

        $this->assertDatabaseHas('captacao_lote_rotas', [
            'id_captacao_lote' => $lote->id,
            'id_captacao_rota' => $c['rota']->id,
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

        $this->assertDatabaseHas('captacao_lote_rotas', [
            'id_captacao_lote' => $lote->id,
            'id_captacao_rota' => $c['rota']->id,
            'id_veiculo' => $veiculo->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rota-'.$c['rota']->id]))
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
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rota-'.$c['rota']->id]))
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

    public function test_motorista_e_veiculo_sao_independentes_entre_lotes(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote1 = $this->criarLoteCaptacao($c);
        $lote2 = $this->criarLoteCaptacao($c);

        $veiculo1 = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'nome' => 'CAMINHAO LOTE 1',
            'status' => 'ATIVO',
        ]);
        $veiculo2 = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'nome' => 'CAMINHAO LOTE 2',
            'status' => 'ATIVO',
        ]);

        foreach ([$lote1, $lote2] as $lote) {
            app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], \App\Enums\PedidoOrigem::Web, $user);
            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 2,
            ])->assertOk();
            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
                'id_captacao_rota' => $c['rota']->id,
            ])->assertOk();
        }

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote1, $c['rota']]), [
                'nome_motorista' => 'Motorista Lote 1',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote1, $c['rota']]), [
                'id_veiculo' => $veiculo1->id,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote2, $c['rota']]), [
                'nome_motorista' => 'Motorista Lote 2',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote2, $c['rota']]), [
                'id_veiculo' => $veiculo2->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('captacao_lote_rotas', [
            'id_captacao_lote' => $lote1->id,
            'id_captacao_rota' => $c['rota']->id,
            'nome_motorista' => 'Motorista Lote 1',
            'id_veiculo' => $veiculo1->id,
        ]);

        $this->assertDatabaseHas('captacao_lote_rotas', [
            'id_captacao_lote' => $lote2->id,
            'id_captacao_rota' => $c['rota']->id,
            'nome_motorista' => 'Motorista Lote 2',
            'id_veiculo' => $veiculo2->id,
        ]);

        $estado1 = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote1))
            ->assertOk()
            ->json();

        $estado2 = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote2))
            ->assertOk()
            ->json();

        $this->assertSame('Motorista Lote 1', $estado1['grupos_ordem_carregamento'][0]['motorista_nome']);
        $this->assertSame($veiculo1->id, $estado1['grupos_ordem_carregamento'][0]['id_veiculo']);
        $this->assertSame('Motorista Lote 2', $estado2['grupos_ordem_carregamento'][0]['motorista_nome']);
        $this->assertSame($veiculo2->id, $estado2['grupos_ordem_carregamento'][0]['id_veiculo']);
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

    public function test_concluir_rota_exige_motorista_veiculo_e_ordem(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, concluirCaptacao: false);

        $respostaIncompleta = $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertStatus(422);
        $pendencias = $respostaIncompleta->json('errors.rota');
        $textoPendencias = is_array($pendencias) ? implode(' ', $pendencias) : (string) $pendencias;
        $this->assertStringContainsString('motorista', $textoPendencias);
        $this->assertStringContainsString('veículo', $textoPendencias);
        $this->assertStringContainsString('ordem de carregamento', $textoPendencias);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote, $c['rota']]), [
            'nome_motorista' => 'João',
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $c['rota']]), [
            'id_veiculo' => $veiculo->id,
        ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertStatus(422);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
            'ordem_carregamento' => 1,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->seedCaptacaoMovimentacao();
        $this->garantirEstoqueGalpaoLote($c, $lote->fresh());

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();
    }

    public function test_concluir_rota_exige_captacao_concluida_em_todas_as_lojas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $clienteB = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);
        $this->prepararLojaComRota($user, $lote, $c, $clienteB, quantidade: 2, ordem: 2, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: false);

        $resposta = $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertStatus(422);

        $this->assertStringContainsString('captação', (string) ($resposta->json('errors.rota.0') ?? ''));
    }

    public function test_concluir_captacao_lote_transiciona_para_captacao_concluida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.concluir-captacao', $lote))
            ->assertRedirect(route('admin.captacao.pedidos-por-loja.lojas', $lote))
            ->assertSessionHas('success');

        $this->assertSame(
            CaptacaoLoteStatus::CaptacaoConcluida,
            $lote->fresh()->status,
        );
    }

    public function test_concluir_captacao_lote_exige_todas_rotas_com_pedido_concluidas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $rotaB = CaptacaoRota::query()->create([
            'id_captacao_carteira' => $c['carteira']->id,
            'nome' => 'Rota B',
            'ativo' => true,
        ]);

        $clienteB = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);

        $veiculoA = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);
        $veiculoB = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculoA, concluirCaptacao: true);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->prepararLojaComRota($user, $lote, $c, $clienteB, quantidade: 3, ordem: 1, motorista: 'Motorista B', veiculo: $veiculoB, rota: $rotaB, concluirCaptacao: false);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $clienteB]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->actingAs($user)
            ->post(route('admin.captacao.pedidos-por-loja.concluir-captacao', $lote))
            ->assertSessionHasErrors('lote');

        $this->assertStringContainsString('Por rota', (string) session('errors')->get('lote')[0] ?? '');
    }

    public function test_reabrir_captacao_loja_bloqueada_quando_rota_concluida(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
                'captacao_concluida' => false,
            ])
            ->assertStatus(422);
    }

    public function test_concluir_rota_na_aba_por_rota_bloqueia_novo_vinculo(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $clienteB = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);
        $this->prepararLojaComRota($user, $lote, $c, $clienteB, quantidade: 2, ordem: 2, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk()
            ->assertJsonPath('grupos_ordem_carregamento.0.concluida', true);

        $this->assertDatabaseHas('captacao_lote_rotas', [
            'id_captacao_lote' => $lote->id,
            'id_captacao_rota' => $c['rota']->id,
            'concluida' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'rota-'.$c['rota']->id]))
            ->assertOk()
            ->assertSee('btn-matriz-rota-reabrir', false)
            ->assertSee('Concluída', false);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $clienteB]), [
                'id_captacao_rota' => null,
            ])
            ->assertStatus(422);

        $clienteC = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteC, [$c['fruta']->id]);
        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $clienteC, \App\Enums\PedidoOrigem::Web, $user);
        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $clienteC->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 1,
        ])->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $clienteC]), [
                'id_captacao_rota' => $c['rota']->id,
            ])
            ->assertStatus(422);

        $rotaB = CaptacaoRota::query()->create([
            'id_captacao_carteira' => $c['carteira']->id,
            'nome' => 'Rota B',
            'ativo' => true,
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $clienteC]), [
                'id_captacao_rota' => $rotaB->id,
            ])
            ->assertOk();
    }

    public function test_reabrir_rota_libera_vinculo_e_ordem(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        \App\Models\Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '0.00',
        ]);

        \App\Models\MovimentacaoEstoque::query()->create([
            'id_estoque' => \App\Models\Estoque::query()
                ->where('id_unidade_negocio', $c['galpao']->id)
                ->where('id_fruta', $c['fruta']->id)
                ->value('id'),
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => true,
        ]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 3, ordem: 1, motorista: 'Motorista B', veiculo: $veiculo, concluirCaptacao: false);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_rota' => $c['rota']->id,
            'tipo' => \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
            'status_demanda' => \App\Enums\CaptacaoDemandaStatus::Aberto->value,
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
                'ordem_carregamento' => 2,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.reabrir', [$lote, $c['rota']]))
            ->assertOk()
            ->assertJsonPath('grupos_ordem_carregamento.0.concluida', false);

        $this->assertSoftDeleted('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'id_captacao_rota' => $c['rota']->id,
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
                'ordem_carregamento' => 1,
            ])
            ->assertOk();
    }

    public function test_reabrir_rota_bloqueada_quando_demanda_concluida(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $this->criarCoGalpao($c['galpao']);

        \App\Models\Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        \App\Models\MovimentacaoEstoque::query()->create([
            'id_estoque' => \App\Models\Estoque::query()
                ->where('id_unidade_negocio', $c['galpao']->id)
                ->where('id_fruta', $c['fruta']->id)
                ->value('id'),
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $vinculoVenda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->where('id_captacao_lote', $lote->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.demandas.venda.efetivar', [$lote, $vinculoVenda]))
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.reabrir', [$lote, $c['rota']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rota']);
    }

    public function test_concluir_rota_bloqueia_motorista_e_veiculo(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 1, ordem: 1, motorista: 'Motorista C', veiculo: $veiculo);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote, $c['rota']]), [
                'nome_motorista' => 'Bloqueado',
            ])
            ->assertStatus(422);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $c['rota']]), [
                'id_veiculo' => $veiculo->id,
            ])
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $c
     */
    private function prepararLojaComRota(
        \App\Models\User $user,
        \App\Models\Captacao\CaptacaoLote $lote,
        array $c,
        Cliente $cliente,
        float $quantidade = 1,
        ?int $ordem = null,
        ?string $motorista = null,
        ?\App\Models\Veiculo $veiculo = null,
        bool $concluirCaptacao = true,
        ?\App\Models\Captacao\CaptacaoRota $rota = null,
    ): void {
        $rota = $rota ?? $c['rota'];

        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $cliente, \App\Enums\PedidoOrigem::Web, $user);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $cliente->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => $quantidade,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $cliente]), [
            'id_captacao_rota' => $rota->id,
        ])->assertOk();

        if ($motorista !== null) {
            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.motorista', [$lote, $rota]), [
                'nome_motorista' => $motorista,
            ])->assertOk();
        }

        if ($veiculo !== null) {
            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.rotas.veiculo', [$lote, $rota]), [
                'id_veiculo' => $veiculo->id,
            ])->assertOk();
        }

        if ($ordem !== null) {
            $this->actingAs($user)->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $cliente]), [
                'ordem_carregamento' => $ordem,
            ])->assertOk();
        }

        $this->seedCaptacaoMovimentacao();
        $this->garantirEstoqueGalpaoLote($c, $lote->fresh());

        if ($concluirCaptacao) {
            $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $cliente]), [
                'captacao_concluida' => true,
            ])->assertOk();
        }
    }

    public function test_concluir_rota_gera_demanda_venda_aberta_sem_movimentacao_imediata(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $this->criarCoGalpao($c['galpao']);

        \App\Models\Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        \App\Models\MovimentacaoEstoque::query()->create([
            'id_estoque' => \App\Models\Estoque::query()
                ->where('id_unidade_negocio', $c['galpao']->id)
                ->where('id_fruta', $c['fruta']->id)
                ->value('id'),
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->assertDatabaseMissing('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'id_captacao_rota' => $c['rota']->id,
            'tipo' => \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
        ]);

        $vinculoVenda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->where('id_captacao_lote', $lote->id)
            ->whereNull('id_pedido')
            ->firstOrFail();

        $this->assertDatabaseHas('captacao_lote_movimentacao_linhas', [
            'id_captacao_lote_movimentacao' => $vinculoVenda->id,
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
        ]);

        $numeroNf = sprintf('CAP-%s-%d-%d', $lote->data_referencia->format('Ymd'), $lote->id, $pedido->id_cliente);
        $this->assertDatabaseMissing('vendas_notas', [
            'numero_nf' => $numeroNf,
        ]);

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id' => $vinculoVenda->id,
            'status_demanda' => \App\Enums\CaptacaoDemandaStatus::Aberto->value,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.demandas.venda.efetivar', [$lote, $vinculoVenda]))
            ->assertOk();

        $nota = \App\Models\VendaNota::query()->where('numero_nf', $numeroNf)->firstOrFail();

        $this->assertDatabaseHas('vendas_notas', [
            'id' => $nota->id,
            'status_conclusao' => \App\Enums\VendaNotaStatusConclusao::Concluida->value,
        ]);

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id' => $vinculoVenda->id,
            'status_demanda' => \App\Enums\CaptacaoDemandaStatus::Concluido->value,
        ]);

        $this->assertDatabaseHas('movimentacoes', [
            'venda_nota_id' => $nota->id,
            'id_fruta' => $c['fruta']->id,
        ]);
    }

    public function test_concluir_rota_gera_demandas_abertas_e_efetiva_apos_ciclo_transferencia(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        \App\Models\Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '0.00',
        ]);

        \App\Models\MovimentacaoEstoque::query()->create([
            'id_estoque' => \App\Models\Estoque::query()
                ->where('id_unidade_negocio', $c['galpao']->id)
                ->where('id_fruta', $c['fruta']->id)
                ->value('id'),
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => true,
        ]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: false);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'id_captacao_rota' => $c['rota']->id,
            'tipo' => \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
            'id_unidade_negocio_origem' => $hub->id,
            'status_demanda' => \App\Enums\CaptacaoDemandaStatus::Aberto->value,
        ]);

        $vinculoTransferencia = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->where('id_captacao_lote', $lote->id)
            ->firstOrFail();

        $this->assertDatabaseHas('captacao_lote_movimentacao_linhas', [
            'id_captacao_lote_movimentacao' => $vinculoTransferencia->id,
            'id_fruta' => $c['fruta']->id,
        ]);

        $this->assertNull($vinculoTransferencia->transferencia_origem_id);

        $vinculoVenda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->where('id_captacao_lote', $lote->id)
            ->whereNull('id_pedido')
            ->firstOrFail();

        $numeroNf = sprintf('CAP-%s-%d-%d', $lote->data_referencia->format('Ymd'), $lote->id, $pedido->id_cliente);
        $this->assertDatabaseMissing('vendas_notas', [
            'numero_nf' => $numeroNf,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.demandas.transferencia.iniciar', [$lote, $vinculoTransferencia]))
            ->assertOk();

        $vinculoTransferencia->refresh();
        $this->assertSame(\App\Enums\CaptacaoDemandaStatus::Iniciado->value, $vinculoTransferencia->status_demanda);

        $arquivoNf = \Illuminate\Http\UploadedFile::fake()->create('nf-transferencia.xml', 10, 'text/xml');

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.demandas.transferencia.nf', [$lote, $vinculoTransferencia]), [
                'arquivo_nf' => $arquivoNf,
            ])
            ->assertOk();

        $vinculoTransferencia->refresh();
        $this->assertNull($vinculoTransferencia->transferencia_origem_id);
        $this->assertSame(\App\Enums\CaptacaoDemandaStatus::Concluido->value, $vinculoTransferencia->status_demanda);

        $this->assertDatabaseMissing('movimentacoes', [
            'numero_nf_origem' => sprintf('CAP-TR-%d-R%d', $lote->id, $c['rota']->id),
            'status_registro' => \App\Enums\MovimentacaoStatusRegistro::ATIVO->value,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.demandas.venda.efetivar', [$lote, $vinculoVenda]))
            ->assertOk();

        $nota = \App\Models\VendaNota::query()->where('numero_nf', $numeroNf)->firstOrFail();

        $this->assertDatabaseHas('vendas_notas', [
            'id' => $nota->id,
            'status_conclusao' => \App\Enums\VendaNotaStatusConclusao::Concluida->value,
        ]);

        $this->assertDatabaseHas('movimentacoes', [
            'venda_nota_id' => $nota->id,
            'id_fruta' => $c['fruta']->id,
        ]);

        $vinculoVenda->refresh();
        $this->assertSame(\App\Enums\CaptacaoDemandaStatus::Concluido->value, $vinculoVenda->status_demanda);
    }

    public function test_concluir_rota_sem_criterios_retorna_pendencias_em_lista(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: null, concluirCaptacao: false);

        $response = $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rota']);

        $pendencias = $response->json('errors.rota');
        $this->assertIsArray($pendencias);
        $this->assertNotEmpty($pendencias);
        $this->assertTrue(collect($pendencias)->contains(
            fn (string $msg): bool => str_contains($msg, 'motorista') || str_contains($msg, 'veículo') || str_contains($msg, 'ordem'),
        ));
    }

    public function test_concluir_rota_bloqueia_sequencia_carregamento_com_lacuna(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $clienteB = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteB, [$c['fruta']->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: false);
        $this->prepararLojaComRota($user, $lote, $c, $clienteB, quantidade: 1, ordem: 2, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: false);

        Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $clienteB->id)
            ->update(['ordem_carregamento' => 3]);

        $response = $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rota']);

        $pendencias = implode(' ', $response->json('errors.rota') ?? []);
        $this->assertStringContainsString('sequência de carregamento', $pendencias);
    }

    public function test_estado_matriz_nao_inclui_demandas_quando_rota_concluida(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        \App\Models\Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '0.00',
        ]);

        \App\Models\MovimentacaoEstoque::query()->create([
            'id_estoque' => \App\Models\Estoque::query()
                ->where('id_unidade_negocio', $c['galpao']->id)
                ->where('id_fruta', $c['fruta']->id)
                ->value('id'),
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => true,
        ]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager([
            \App\Enums\Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
            \App\Enums\Permissions::MOVIMENTACOES_VENDAS_VISUALIZAR,
        ]);
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: false);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk()
            ->assertJsonPath('grupos_ordem_carregamento.0.concluida', true)
            ->assertJsonPath('grupos_ordem_carregamento.0.demandas', []);

        $demandas = $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->json('grupos_ordem_carregamento.0.demandas');

        $this->assertSame([], $demandas);

        $transferencias = $this->actingAs($user)
            ->get(route('admin.movimentacoes.transferencias.index'))
            ->assertOk()
            ->viewData('demandasCards');

        $this->assertNotEmpty($transferencias);
        $this->assertNotNull(collect($transferencias)->firstWhere('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA));

        $vendas = $this->actingAs($user)
            ->get(route('admin.movimentacoes.vendas.index'))
            ->assertOk()
            ->viewData('demandasCards');

        $this->assertNotEmpty($vendas);
        $this->assertNotNull(collect($vendas)->firstWhere('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA));
    }

    public function test_concluir_rota_gera_uma_demanda_transferencia_agregada_multi_fruta(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $fruta2 = \App\Models\Fruta::factory()->create(['kg_por_unidade_medicao' => '10.00']);
        app(\App\Services\Captacao\ClienteFrutaVinculoService::class)
            ->sincronizarFrutas($c['cliente'], [$c['fruta']->id, $fruta2->id]);

        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $estoqueFruta2 = \App\Models\Estoque::factory()->create([
            'id_unidade_negocio' => $hub->id,
            'id_fruta' => $fruta2->id,
            'qtd_fruta_kg' => '200.00',
            'qtd_fruta_um' => '20.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '1000.00',
        ]);
        \App\Models\MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueFruta2->id,
            'id_unidade_negocio' => $hub->id,
            'id_fruta' => $fruta2->id,
            'qtd_fruta_kg' => '200.00',
            'qtd_fruta_um' => '20.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '1000.00',
            'status_ultima_posicao' => true,
        ]);

        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: false);

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $fruta2->id,
            'quantidade' => 3,
            'preco_venda' => 10,
        ])->assertOk();

        $this->garantirEstoqueGalpaoLote($c, $lote->fresh());

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.pedidos.captacao-concluida', [$lote, $c['cliente']]), [
            'captacao_concluida' => true,
        ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $this->assertSame(1, \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $c['rota']->id)
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->count());

        $demanda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->where('id_captacao_lote', $lote->id)
            ->firstOrFail();

        $this->assertDatabaseHas('captacao_lote_movimentacao_linhas', [
            'id_captacao_lote_movimentacao' => $demanda->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_um' => '2.000',
        ]);
        $this->assertDatabaseHas('captacao_lote_movimentacao_linhas', [
            'id_captacao_lote_movimentacao' => $demanda->id,
            'id_fruta' => $fruta2->id,
            'qtd_um' => '3.000',
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.demandas.transferencia.iniciar', [$lote, $demanda]))
            ->assertOk();

        $arquivoNf = \Illuminate\Http\UploadedFile::fake()->create('nf-transferencia.xml', 10, 'text/xml');

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.demandas.transferencia.nf', [$lote, $demanda]), [
                'arquivo_nf' => $arquivoNf,
            ])
            ->assertOk();

        $demanda->refresh();
        $this->assertNull($demanda->transferencia_origem_id);
        $this->assertSame(\App\Enums\CaptacaoDemandaStatus::Concluido->value, $demanda->status_demanda);

        $this->assertDatabaseMissing('movimentacoes', [
            'numero_nf_origem' => sprintf('CAP-TR-%d-R%d', $lote->id, $c['rota']->id),
            'status_registro' => \App\Enums\MovimentacaoStatusRegistro::ATIVO->value,
        ]);
    }

    public function test_reverter_movimentacao_sb_indesejada_em_demanda_automatica(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $demanda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->firstOrFail();

        $demanda->update([
            'transferencia_origem_id' => 99999,
            'status_demanda' => \App\Enums\CaptacaoDemandaStatus::Concluido->value,
        ]);

        $empresaHub = $hub->registroCorporativo()->firstOrFail();
        $empresaGalpao = $c['galpao']->registroCorporativo()->firstOrFail();

        $par = app(\App\Services\Movimentacoes\TransferenciaMovimentacaoService::class)
            ->criarTransferenciaAguardandoRecebimento([
                'id_empresa_origem' => $empresaHub->id,
                'id_empresa_destino' => $empresaGalpao->id,
                'id_fruta' => $c['fruta']->id,
                'qtd_fruta_um' => '2.00',
                'numero_nf_origem' => sprintf('CAP-TR-%d-R%d', $lote->id, $c['rota']->id),
            ]);

        $anchor = (int) $par['saida']->transferencia_origem_id;
        $demanda->update(['transferencia_origem_id' => $anchor]);

        app(\App\Services\Captacao\CaptacaoDemandaTransferenciaRotaService::class)
            ->reverterMovimentacaoSbIndevida($demanda);

        $demanda->refresh();
        $this->assertNull($demanda->transferencia_origem_id);

        $this->assertDatabaseHas('movimentacoes', [
            'transferencia_origem_id' => $anchor,
            'status_registro' => \App\Enums\MovimentacaoStatusRegistro::CANCELADO->value,
        ]);
    }

    public function test_excluir_demanda_transferencia_automatica_da_rota_bloqueada(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $demanda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->firstOrFail();

        $this->actingAs($user)
            ->deleteJson(route('admin.captacao.lotes.demandas.transferencia.excluir', [$lote, $demanda]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['demanda']);
    }

    public function test_download_cigam_demanda_transferencia_no_modulo_movimentacoes(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        \App\Models\Estoque::factory()->create([
            'id_unidade_negocio' => $c['galpao']->id,
            'id_fruta' => $c['fruta']->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager([
            \App\Enums\Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
        ]);
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $demanda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.demandas.transferencia.iniciar', [$lote, $demanda]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.movimentacoes.transferencias.demandas-captacao.cigam', $demanda))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_demanda_transferencia_exibe_romaneio_por_loja_com_origem_fiscal(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '100.00', '10.00');
        $this->criarCoGalpao($c['faturamento']);
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['id_unidade_negocio_hub_origem' => $hub->id]);

        $user = $this->captacaoManager([
            \App\Enums\Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
        ]);
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $veiculo = \App\Models\Veiculo::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'status' => 'ATIVO',
        ]);

        $this->prepararLojaComRota($user, $lote, $c, $c['cliente'], quantidade: 2, ordem: 1, motorista: 'Motorista A', veiculo: $veiculo, concluirCaptacao: true);

        $pedido = Pedido::query()->where('id_captacao_lote', $lote->id)->where('id_cliente', $c['cliente']->id)->firstOrFail();
        $pedido->update(['id_unidade_negocio_saida_venda' => $hub->id]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.rotas.concluir', [$lote, $c['rota']]))
            ->assertOk();

        $demanda = \App\Models\Captacao\CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->firstOrFail();

        $nomeLoja = $c['cliente']->fantasia ?: $c['cliente']->razao_social;

        $this->actingAs($user)
            ->get(route('admin.movimentacoes.transferencias.demandas-captacao.show', $demanda))
            ->assertOk()
            ->assertSee('Origem fiscal (saída venda)', false)
            ->assertSee('Motivo da transferência', false)
            ->assertSee('Unidade de faturamento', false)
            ->assertSee('fiscal somente no CIGAM', false)
            ->assertSee($c['faturamento']->nome, false)
            ->assertSee($nomeLoja, false)
            ->assertSee($hub->nome, false);
    }
}
