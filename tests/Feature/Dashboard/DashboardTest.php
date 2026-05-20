<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Roles;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EstadoSeeder::class);
    }

    public function test_dashboard_exibe_totais_da_unidade_vinculada(): void
    {
        $unidadeA = UnidadeNegocio::factory()->create(['nome' => 'UNIDADE ALPHA']);
        $unidadeB = UnidadeNegocio::factory()->create(['nome' => 'UNIDADE BETA']);

        Cliente::factory()->count(2)->create(['id_unidade_negocio' => $unidadeA->id]);
        Cliente::factory()->create(['id_unidade_negocio' => $unidadeB->id]);

        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $user->unidadesNegocio()->sync([$unidadeA->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('UNIDADE ALPHA')
            ->assertSee('Resumo operacional')
            ->assertDontSee('UNIDADE BETA')
            ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['totais']['clientes'] === 2);
    }

    public function test_administrador_ve_todas_as_unidades(): void
    {
        UnidadeNegocio::factory()->count(2)->create();

        $admin = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $admin->assignRole(Role::findOrCreate(Roles::ADMINISTRADOR->value, 'web'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Visão de todas as unidades de negócio');
    }
}
