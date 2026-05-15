<?php

namespace Tests\Feature\Admin\Pracas;

use App\Enums\Permissions;
use App\Models\Praca;
use App\Models\UnidadeNegocio;

class PracaTest extends PracaTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.pracas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.pracas.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::PRACAS_VISUALIZAR]))
            ->get(route('admin.pracas.index'))
            ->assertOk();
    }

    public function test_listagem_ajax_retorna_partial_da_tabela(): void
    {
        $unidade = UnidadeNegocio::factory()->create();
        Praca::factory()->create([
            'nome' => 'PRACA AJAX',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $this->actingAs($this->pracasManager())
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html',
            ])
            ->get(route('admin.pracas.index'))
            ->assertOk()
            ->assertSee('PRACA AJAX', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $unidade = UnidadeNegocio::factory()->create();
        $payload = $this->pracaPayload([
            'nome' => 'praça sul',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::PRACAS_CRIAR]))
            ->post(route('admin.pracas.store'), $payload)
            ->assertRedirect(route('admin.pracas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pracas', [
            'nome' => 'PRAÇA SUL',
            'id_unidade_negocio' => $unidade->id,
        ]);
    }

    public function test_nome_duplicado_na_mesma_unidade_falha_na_validacao(): void
    {
        $unidade = UnidadeNegocio::factory()->create();
        Praca::factory()->create([
            'nome' => 'DUPLICADA',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::PRACAS_CRIAR]))
            ->post(route('admin.pracas.store'), $this->pracaPayload([
                'nome' => 'duplicada',
                'id_unidade_negocio' => $unidade->id,
            ]))
            ->assertSessionHasErrors('nome');
    }

    public function test_mesmo_nome_em_unidades_diferentes_e_permitido(): void
    {
        $unidadeA = UnidadeNegocio::factory()->create();
        $unidadeB = UnidadeNegocio::factory()->create();

        Praca::factory()->create([
            'nome' => 'CENTRO',
            'id_unidade_negocio' => $unidadeA->id,
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::PRACAS_CRIAR]))
            ->post(route('admin.pracas.store'), $this->pracaPayload([
                'nome' => 'centro',
                'id_unidade_negocio' => $unidadeB->id,
            ]))
            ->assertRedirect(route('admin.pracas.index'));
    }

    public function test_edicao_atualiza_registro(): void
    {
        $unidadeAntiga = UnidadeNegocio::factory()->create();
        $unidadeNova = UnidadeNegocio::factory()->create();
        $praca = Praca::factory()->create([
            'nome' => 'ANTES',
            'id_unidade_negocio' => $unidadeAntiga->id,
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::PRACAS_EDITAR]))
            ->put(route('admin.pracas.update', $praca), [
                'nome' => 'Depois',
                'id_unidade_negocio' => $unidadeNova->id,
            ])
            ->assertRedirect(route('admin.pracas.index'))
            ->assertSessionHas('success');

        $praca->refresh();
        $this->assertSame('DEPOIS', $praca->nome);
        $this->assertSame($unidadeNova->id, $praca->id_unidade_negocio);
    }
}
