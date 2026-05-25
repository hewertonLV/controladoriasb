<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\Permissions;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Services\Captacao\ClienteFrutaVinculoService;

class ClienteFrutaVinculoTest extends CaptacaoTestCase
{
    public function test_sincronizar_frutas_define_colunas_da_matriz(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $fruta2 = \App\Models\Fruta::factory()->create(['nome' => 'MANGA TESTE']);

        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($c['cliente'], [
            $c['fruta']->id,
            $fruta2->id,
        ]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        app(\App\Services\Captacao\PedidoService::class)->adicionarLojaNaMatriz(
            $lote,
            $c['cliente'],
            \App\Enums\PedidoOrigem::Web,
            null,
        );

        $dados = app(ClienteFrutaVinculoService::class)->dadosMatriz($lote->fresh());

        $this->assertCount(1, $dados['clientes']);
        $this->assertCount(2, $dados['frutas']);
        $this->assertContains($c['fruta']->id, $dados['frutasPorCliente'][$c['cliente']->id]);
    }

    public function test_usuario_so_visualizar_ve_botao_detalhe_sem_salvar(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]);

        $this->actingAs($user)
            ->get(route('admin.captacao.frutas-por-loja.index', ['faturamento' => $c['faturamento']->id]))
            ->assertOk()
            ->assertSee('Detalhe', false)
            ->assertDontSee('Salvar vínculos', false);

        $this->actingAs($user)
            ->get(route('admin.captacao.frutas-por-loja.show', $c['cliente']))
            ->assertOk()
            ->assertSee('Vincular frutas a esta loja', false)
            ->assertDontSee('Salvar vínculos', false);

        $this->actingAs($user)
            ->put(route('admin.captacao.clientes.frutas.sync', $c['cliente']), [
                'id_frutas' => [$c['fruta']->id],
            ])
            ->assertForbidden();
    }

    public function test_tela_frutas_por_loja_e_salvamento_em_lote(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $this->actingAs($user)
            ->get(route('admin.captacao.frutas-por-loja.index', ['faturamento' => $c['faturamento']->id]))
            ->assertOk()
            ->assertSee('Detalhe', false)
            ->assertSee('CLIENTE CAPTACAO TESTE', false);

        $this->actingAs($user)
            ->get(route('admin.captacao.frutas-por-loja.show', $c['cliente']))
            ->assertOk()
            ->assertSee('Salvar vínculos', false);

        $this->actingAs($user)
            ->put(route('admin.captacao.clientes.frutas.sync', $c['cliente']), [
                'id_frutas' => [$c['fruta']->id],
            ])
            ->assertRedirect(route('admin.captacao.frutas-por-loja.show', $c['cliente']));

        $this->assertDatabaseHas('cliente_fruta_vinculos', [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'ativo' => true,
        ]);
    }

    public function test_pedido_rejeita_fruta_nao_vinculada(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $outraFruta = \App\Models\Fruta::factory()->create();

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pedidos.store', $lote), [
                'id_cliente' => $c['cliente']->id,
                'itens' => [['id_fruta' => $outraFruta->id, 'quantidade' => 1]],
            ])
            ->assertSessionHasErrors('id_fruta');
    }

    public function test_matriz_exibe_colunas_apos_adicionar_loja(): void
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

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
                'id_cliente' => $c['cliente']->id,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertOk()
            ->assertSee($c['fruta']->nome, false)
            ->assertSee('Selecione a loja', false);
    }

    public function test_adicionar_segunda_loja_mescla_colunas_de_frutas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $fruta2 = \App\Models\Fruta::factory()->create(['nome' => 'UVA TESTE']);
        $cliente2 = \App\Models\Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($cliente2, [$fruta2->id]);

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
        $this->actingAs($user)->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
            'id_cliente' => $cliente2->id,
        ]);

        $dados = app(ClienteFrutaVinculoService::class)->dadosMatriz($lote->fresh());

        $this->assertCount(2, $dados['clientes']);
        $this->assertCount(2, $dados['frutas']);
        $this->assertSame(2, $dados['frutas']->pluck('id')->unique()->count());
        $this->assertSame(
            [$c['cliente']->id, $cliente2->id],
            $dados['clientes']->pluck('id')->all(),
        );
    }

    public function test_matriz_rejeita_edicao_quando_lote_nao_esta_em_captacao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
                'id_cliente' => $c['cliente']->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O lote não está em captação.')
            ->assertJsonPath('code', 'captacao_edicao_bloqueada');
    }

    public function test_matriz_ordenacao_por_ordem_de_inclusao_nao_alfabetica(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $clienteZ = \App\Models\Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'razao_social' => 'ZZZ LOJA TESTE',
            'fantasia' => 'ZZZ LOJA TESTE',
        ]);
        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteZ, [$c['fruta']->id]);

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
            'id_cliente' => $clienteZ->id,
        ]);
        $this->actingAs($user)->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
            'id_cliente' => $c['cliente']->id,
        ]);

        $dados = app(ClienteFrutaVinculoService::class)->dadosMatriz($lote->fresh());

        $this->assertSame(
            [$clienteZ->id, $c['cliente']->id],
            $dados['clientes']->pluck('id')->all(),
        );
    }
}
