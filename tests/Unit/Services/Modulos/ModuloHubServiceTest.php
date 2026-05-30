<?php

namespace Tests\Unit\Services\Modulos;

use App\Enums\AppModulo;
use App\Enums\Permissions;
use App\Enums\Roles;
use App\Models\User;
use App\Services\Modulos\ModuloHubService;
use App\Services\Modulos\RoleModuloService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class ModuloHubServiceTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    public function test_modulos_disponiveis_respeita_vinculos_do_grupo(): void
    {
        $admin = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $admin->assignRole(Role::findOrCreate(Roles::ADMINISTRADOR->value, 'web'));

        $vendedor = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
        ]);

        app(RoleModuloService::class)->sincronizarModulos(
            $vendedor->roles()->first(),
            [AppModulo::Transferencia],
        );

        $hub = app(ModuloHubService::class);

        $modulosAdmin = $hub->modulosDisponiveis($admin)->pluck('modulo')->all();
        $modulosVendedor = $hub->modulosDisponiveis($vendedor)->pluck('modulo')->all();

        $this->assertContains(AppModulo::Administrador, $modulosAdmin);
        $this->assertContains(AppModulo::Centralizador, $modulosAdmin);
        $this->assertSame([AppModulo::Transferencia], $modulosVendedor);
    }

    public function test_vendedor_padrao_nao_recebe_modulo_centralizador(): void
    {
        $vendedor = $this->vendedorUser();
        $hub = app(ModuloHubService::class);

        $modulos = $hub->modulosDisponiveis($vendedor)->pluck('modulo')->all();

        $this->assertContains(AppModulo::Captacao, $modulos);
        $this->assertNotContains(AppModulo::Centralizador, $modulos);
    }
}
