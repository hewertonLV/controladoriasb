<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\Fruta;

class FrutaTest extends FrutaTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.frutas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.frutas.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_VISUALIZAR]))
            ->get(route('admin.frutas.index'))
            ->assertOk();
    }

    public function test_listagem_usa_datatable_com_registros(): void
    {
        Fruta::factory()->create(['nome' => 'MANGA DATATABLE']);

        $this->actingAs($this->frutasManager())
            ->get(route('admin.frutas.index'))
            ->assertOk()
            ->assertSee('MANGA DATATABLE', false)
            ->assertSee('id="frutas-datatable"', false)
            ->assertSee('data-admin-datatable', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $payload = $this->frutaPayload([
            'id_cigam' => '1.2-3',
            'nome' => 'maçã fuji',
            'unidade_medicao' => 'caixa',
            'kg_por_unidade_medicao' => '12.5',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $payload)
            ->assertRedirect(route('admin.frutas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('frutas', [
            'id_cigam' => '000123',
            'nome' => 'MAÇÃ FUJI',
            'unidade_medicao' => 'CAIXA',
            'kg_por_unidade_medicao' => '12.50',
        ]);
    }

    public function test_id_cigam_duplicado_falha_na_validacao(): void
    {
        Fruta::factory()->create(['id_cigam' => '000099']);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'id_cigam' => '99',
            ]))
            ->assertSessionHasErrors('id_cigam');
    }

    public function test_unidade_medicao_invalida_falha(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'unidade_medicao' => 'INVALIDA',
            ]))
            ->assertSessionHasErrors('unidade_medicao');
    }

    public function test_kg_negativo_falha(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_CRIAR]))
            ->post(route('admin.frutas.store'), $this->frutaPayload([
                'kg_por_unidade_medicao' => -1,
            ]))
            ->assertSessionHasErrors('kg_por_unidade_medicao');
    }

    public function test_edicao_atualiza_registro(): void
    {
        $fruta = Fruta::factory()->create([
            'id_cigam' => '000050',
            'nome' => 'ANTES',
            'unidade_medicao' => FrutaUnidadeMedicao::PACOTE->value,
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FRUTAS_EDITAR]))
            ->put(route('admin.frutas.update', $fruta), $this->frutaPayload([
                'id_cigam' => '50',
                'nome' => 'Depois',
                'unidade_medicao' => FrutaUnidadeMedicao::SACO->value,
                'kg_por_unidade_medicao' => '20',
                'icms' => [
                    Estado::ID_CEARA => [
                        'entrada_externo' => '1.00',
                        'entrada_um_externo' => FrutaUmIcms::UM->value,
                        'entrada_nacional' => '2.00',
                        'entrada_um_nacional' => FrutaUmIcms::UM->value,
                        'saida_importada' => '5.00',
                        'saida_um_importada' => FrutaUmIcms::KG->value,
                        'saida_nacional' => '18.00',
                        'saida_um_nacional' => FrutaUmIcms::KG->value,
                    ],
                ],
            ]))
            ->assertRedirect(route('admin.frutas.index'))
            ->assertSessionHas('success');

        $fruta->refresh();
        $this->assertSame('000050', $fruta->id_cigam);
        $this->assertSame('DEPOIS', $fruta->nome);
        $this->assertSame(FrutaUnidadeMedicao::SACO->value, $fruta->unidade_medicao);
        $this->assertSame('20.00', $fruta->kg_por_unidade_medicao);
        $icmsEntrada = $fruta->icms()->where('id_estado', Estado::ID_CEARA)->where('operacao', 'ENTRADA')->first();
        $this->assertNotNull($icmsEntrada);
        $this->assertSame(FrutaUmIcms::UM->value, $icmsEntrada->um_icms_nacional);
    }
}
