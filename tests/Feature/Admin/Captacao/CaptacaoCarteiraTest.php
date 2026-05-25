<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\ClienteCaptacaoAgendaTipo;
use App\Enums\PedidoOrigem;
use App\Models\Captacao\ClienteCaptacaoAgenda;
use App\Models\Cliente;
use App\Services\Captacao\PedidoService;

class CaptacaoCarteiraTest extends CaptacaoTestCase
{
    public function test_abre_captacao_por_carteira(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.store'), [
                'data_referencia' => '2026-05-30',
                'id_captacao_carteira' => $c['carteira']->id,
            ])
            ->assertRedirect();

        $this->assertTrue(
            \App\Models\Captacao\CaptacaoLote::query()
                ->where('id_captacao_carteira', $c['carteira']->id)
                ->whereDate('data_referencia', '2026-05-30')
                ->exists()
        );
    }

    public function test_consulta_clientes_sem_pedido_na_carteira(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $data = '2026-06-15';

        $outro = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'LOJA SEM PEDIDO TESTE',
        ]);

        $lote = $this->criarLoteCaptacao($c, $data);
        app(PedidoService::class)->adicionarLojaNaMatriz($lote, $c['cliente'], PedidoOrigem::Web, $user);

        $semPedido = app(\App\Services\Captacao\Alertas\ClientesSemPedidoCarteiraQuery::class)
            ->executar($data, $c['carteira']->id);

        $this->assertCount(1, $semPedido);
        $this->assertSame($outro->id, $semPedido->first()['id_cliente']);
        $outro->refresh();
        $this->assertSame(
            (string) ($outro->fantasia ?: $outro->razao_social),
            $semPedido->first()['cliente_nome'],
        );

        $this->actingAs($user)
            ->get(route('admin.captacao.consulta.sem-pedido', [
                'data_referencia' => $data,
                'id_captacao_carteira' => $c['carteira']->id,
            ]))
            ->assertOk()
            ->assertViewHas('clientesSemPedido', fn ($lista) => $lista->count() === 1);
    }

    public function test_matriz_lista_todos_clientes_da_carteira(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['galpao']->id]);

        $semFruta = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'LOJA SEM FRUTA CARTEIRA',
        ]);

        $lote = $this->criarLoteCaptacao($c);

        $disponiveis = app(\App\Services\Captacao\ClienteFrutaVinculoService::class)
            ->clientesDisponiveisParaMatriz($lote->fresh());

        $this->assertTrue($disponiveis->contains('id', $semFruta->id));
    }

    public function test_listagem_separa_abas_ativas_e_inativas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $this->actingAs($user)
            ->get(route('admin.captacao.carteiras.index', ['aba' => 'ativas']))
            ->assertOk()
            ->assertSee('Carteiras ativas', false)
            ->assertSee($c['carteira']->nome, false);

        $this->actingAs($user)
            ->get(route('admin.captacao.carteiras.index', ['aba' => 'inativas']))
            ->assertOk()
            ->assertSee('Carteiras inativas', false)
            ->assertDontSee($c['carteira']->nome, false);
    }

    public function test_nao_inativa_carteira_com_loja_vinculada(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $this->actingAs($user)
            ->post(route('admin.captacao.carteiras.inativar', $c['carteira']))
            ->assertRedirect(route('admin.captacao.carteiras.index', ['aba' => 'ativas']))
            ->assertSessionHas('error');

        $this->assertTrue($c['carteira']->fresh()->ativo);
    }

    public function test_inativa_e_reativa_carteira_sem_lojas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $c['cliente']->update(['id_captacao_carteira' => null]);

        $this->actingAs($user)
            ->post(route('admin.captacao.carteiras.inativar', $c['carteira']))
            ->assertRedirect(route('admin.captacao.carteiras.index', ['aba' => 'inativas']))
            ->assertSessionHas('success');

        $this->assertFalse($c['carteira']->fresh()->ativo);

        $this->actingAs($user)
            ->post(route('admin.captacao.carteiras.reativar', $c['carteira']))
            ->assertRedirect(route('admin.captacao.carteiras.index', ['aba' => 'ativas']))
            ->assertSessionHas('success');

        $this->assertTrue($c['carteira']->fresh()->ativo);
    }

    public function test_edicao_lista_lojas_sem_carteira_no_faturamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $semCarteira = Cliente::factory()->create([
            'id_unidade_negocio' => $c['cliente']->id_unidade_negocio,
            'id_praca' => $c['cliente']->id_praca,
            'id_captacao_carteira' => null,
            'razao_social' => 'LOJA LIVRE CARTEIRA TESTE',
        ]);
        $this->assertNull($semCarteira->fresh()->id_captacao_carteira);

        $outraUn = \App\Models\UnidadeNegocio::factory()->create([
            'emite_nota_fiscal' => true,
            'is_hub' => false,
        ]);
        Cliente::factory()->create([
            'id_unidade_negocio' => $outraUn->id,
            'id_captacao_carteira' => null,
            'razao_social' => 'LOJA OUTRO FATURAMENTO',
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.carteiras.edit', $c['carteira']))
            ->assertOk()
            ->assertViewHas('lojasSemCarteira', fn ($lojas) => $lojas->contains('id', $semCarteira->id))
            ->assertViewHas('lojasVinculadas', fn ($lojas) => $lojas->contains('id', $c['cliente']->id))
            ->assertSee('LOJA LIVRE CARTEIRA TESTE', false)
            ->assertSee('disponíveis para vincular', false)
            ->assertDontSee('LOJA OUTRO FATURAMENTO', false);
    }

    public function test_atualizacao_vincula_e_desvincula_lojas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $nova = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => null,
            'razao_social' => 'LOJA PARA VINCULAR',
        ]);

        $this->actingAs($user)
            ->put(route('admin.captacao.carteiras.update', $c['carteira']), [
                'nome' => $c['carteira']->nome,
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_unidade_negocio_galpao' => $c['galpao']->id,
                'id_clientes' => [$nova->id],
            ])
            ->assertRedirect(route('admin.captacao.carteiras.index', ['aba' => 'ativas']));

        $this->assertDatabaseHas('clientes', [
            'id' => $nova->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        $this->assertDatabaseHas('clientes', [
            'id' => $c['cliente']->id,
            'id_captacao_carteira' => null,
        ]);
    }

    public function test_nao_vincula_loja_de_outra_carteira(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $galpao2 = \App\Models\UnidadeNegocio::factory()->galpaoOperacional()->create();
        $outraCarteira = \App\Models\Captacao\CaptacaoCarteira::query()->create([
            'nome' => 'OUTRA CARTEIRA TESTE',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $galpao2->id,
            'ativo' => true,
        ]);

        $ocupada = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $outraCarteira->id,
            'razao_social' => 'LOJA OCUPADA',
        ]);

        $this->actingAs($user)
            ->put(route('admin.captacao.carteiras.update', $c['carteira']), [
                'nome' => $c['carteira']->nome,
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_unidade_negocio_galpao' => $c['galpao']->id,
                'id_clientes' => [$ocupada->id],
            ])
            ->assertRedirect(route('admin.captacao.carteiras.edit', $c['carteira']))
            ->assertSessionHasErrors('id_clientes');
    }

    public function test_permite_cadastrar_carteira_com_mesmo_par_faturamento_galpao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $this->actingAs($user)
            ->post(route('admin.captacao.carteiras.store'), [
                'nome' => 'Carteira Litoral Barbalha 01',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_unidade_negocio_galpao' => $c['galpao']->id,
            ])
            ->assertRedirect(route('admin.captacao.carteiras.index', ['aba' => 'ativas']))
            ->assertSessionHas('success');

        $this->assertSame(
            2,
            \App\Models\Captacao\CaptacaoCarteira::query()
                ->where('id_unidade_negocio_faturamento', $c['faturamento']->id)
                ->where('id_unidade_negocio_galpao', $c['galpao']->id)
                ->count(),
        );

        $this->assertDatabaseHas('captacao_carteiras', [
            'nome' => 'Carteira Litoral Barbalha 01',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'ativo' => true,
        ]);
    }

    public function test_permite_atualizar_carteira_para_par_ja_usado_por_outra(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $galpao2 = \App\Models\UnidadeNegocio::factory()->galpaoOperacional()->create();
        $outraCarteira = \App\Models\Captacao\CaptacaoCarteira::query()->create([
            'nome' => 'CARTEIRA PAR DUPLICADO TESTE',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $galpao2->id,
            'ativo' => true,
        ]);

        $this->actingAs($user)
            ->put(route('admin.captacao.carteiras.update', $outraCarteira), [
                'nome' => 'CARTEIRA PAR DUPLICADO TESTE',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_unidade_negocio_galpao' => $c['galpao']->id,
                'id_clientes' => [],
            ])
            ->assertRedirect(route('admin.captacao.carteiras.index', ['aba' => 'ativas']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('captacao_carteiras', [
            'id' => $outraCarteira->id,
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
        ]);
    }

    public function test_cliente_salva_agenda_criacao_e_envio(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([\App\Enums\Permissions::CLIENTES_EDITAR]);

        $this->actingAs($user)
            ->put(route('admin.clientes.update', $c['cliente']), [
                'id_cigam' => $c['cliente']->id_cigam,
                'razao_social' => $c['cliente']->razao_social,
                'fantasia' => $c['cliente']->fantasia,
                'cnpj_cpf' => $c['cliente']->cnpj_cpf,
                'id_unidade_negocio' => $c['faturamento']->id,
                'id_praca' => $c['cliente']->id_praca,
                'grupo_id' => $c['cliente']->grupo_id,
                'desconto_nf' => $c['cliente']->desconto_nf,
                'id_captacao_carteira' => $c['carteira']->id,
                'dias_criacao_pedido' => [2],
                'dias_envio_pedido' => [4],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cliente_captacao_agenda', [
            'id_cliente' => $c['cliente']->id,
            'dia_semana' => 2,
            'tipo' => ClienteCaptacaoAgendaTipo::CriacaoPedido->value,
        ]);
        $this->assertDatabaseHas('cliente_captacao_agenda', [
            'id_cliente' => $c['cliente']->id,
            'dia_semana' => 4,
            'tipo' => ClienteCaptacaoAgendaTipo::EnvioPedido->value,
        ]);
    }
}
