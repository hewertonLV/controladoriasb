<?php

namespace Tests\Feature\Admin\Estados;

use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\Fornecedor;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class EstadoAdminTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoSeeder::class);
    }

    public function test_convidado_redireciona_para_login(): void
    {
        $this->get(route('admin.estados.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.estados.index'))
            ->assertForbidden();
    }

    public function test_listagem_com_datatable(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::ESTADOS_VISUALIZAR]))
            ->get(route('admin.estados.index'))
            ->assertOk()
            ->assertSee('CEARA', false)
            ->assertSee('id="estados-datatable"', false);
    }

    public function test_cadastro_normaliza_campos(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::ESTADOS_CRIAR]))
            ->post(route('admin.estados.store'), [
                'id_cigam' => '99',
                'nome' => '  tocantins  ',
                'abreviacao' => ' to ',
                'descricao' => 'Teste ICMS',
            ])
            ->assertRedirect(route('admin.estados.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('estados', [
            'id_cigam' => '000099',
            'nome' => 'TOCANTINS',
            'abreviacao' => 'TO',
            'descricao' => 'Teste ICMS',
            'deleted_at' => null,
        ]);
    }

    public function test_inativar_estado_sem_vinculos(): void
    {
        $estado = Estado::factory()->create();

        $this->actingAs($this->userWithPermissions([Permissions::ESTADOS_INATIVAR]))
            ->post(route('admin.estados.inativar', $estado))
            ->assertRedirect(route('admin.estados.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('estados', ['id' => $estado->id]);
    }

    public function test_nao_inativa_estado_com_fornecedor_vinculado(): void
    {
        $estado = Estado::query()->findOrFail(Estado::ID_CEARA);
        Fornecedor::factory()->create(['id_estado' => $estado->id]);

        $this->actingAs($this->userWithPermissions([Permissions::ESTADOS_INATIVAR]))
            ->post(route('admin.estados.inativar', $estado))
            ->assertRedirect(route('admin.estados.index'))
            ->assertSessionHas('error');

        $this->assertNull($estado->fresh()->deleted_at);
    }

    public function test_reativar_estado_inativo(): void
    {
        $estado = Estado::factory()->create();
        $estado->delete();

        $this->actingAs($this->userWithPermissions([Permissions::ESTADOS_REATIVAR]))
            ->post(route('admin.estados.reativar', $estado))
            ->assertRedirect(route('admin.estados.index'))
            ->assertSessionHas('success');

        $this->assertNull($estado->fresh()->deleted_at);
    }
}
