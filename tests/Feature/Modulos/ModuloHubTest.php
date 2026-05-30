<?php

namespace Tests\Feature\Modulos;

use App\Enums\AppModulo;
use App\Enums\Permissions;
use App\Models\User;
use App\Services\Modulos\RoleModuloService;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class ModuloHubTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EstadoSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    public function test_hub_lista_tres_modulos_para_grupo_vendedor(): void
    {
        $user = $this->vendedorUser();

        $response = $this->actingAs($user)->get(route('modulos.index'));

        $response
            ->assertOk()
            ->assertSee('Captação')
            ->assertSee('Transferência')
            ->assertSee('Venda')
            ->assertDontSee('Administrador')
            ->assertDontSee('Centralizador');
    }

    public function test_hub_lista_apenas_modulos_permitidos_ao_vendedor(): void
    {
        $user = $this->userWithPermissions([
            Permissions::CAPTACAO_LOTE_VISUALIZAR,
            Permissions::MOVIMENTACOES_VENDAS_VISUALIZAR,
        ]);

        $this->assignModulosToUser($user, [
            AppModulo::Captacao,
            AppModulo::Venda,
        ]);

        $response = $this->actingAs($user)->get(route('modulos.index'));

        $response
            ->assertOk()
            ->assertSee('Captação')
            ->assertSee('Venda')
            ->assertDontSee('Administrador')
            ->assertDontSee('Transferência')
            ->assertDontSee('Centralizador');
    }

    public function test_entrar_em_modulo_centralizador_grava_sessao_e_redireciona(): void
    {
        $user = $this->userWithPermissions([
            Permissions::CAPTACAO_LOTE_VISUALIZAR,
        ]);

        $this->assignModulosToUser($user, [AppModulo::Centralizador]);

        $response = $this->actingAs($user)->get(route('modulos.entrar', AppModulo::Centralizador));

        $response->assertRedirect(route('admin.captacao.lotes.index'));
        $this->assertSame(AppModulo::Centralizador->value, session('app_modulo'));
    }

    public function test_modulo_centralizador_exibe_topbar_operacional_em_lotes(): void
    {
        $user = $this->userWithPermissions([
            Permissions::CAPTACAO_LOTE_VISUALIZAR,
        ]);

        $this->assignModulosToUser($user, [AppModulo::Centralizador]);

        $this->actingAs($user)
            ->get(route('modulos.entrar', AppModulo::Centralizador))
            ->assertRedirect();

        $response = $this->actingAs($user)->get(route('admin.captacao.lotes.index'));

        $response
            ->assertOk()
            ->assertSee('Módulos', false)
            ->assertSee('Criar Captação', false)
            ->assertSee('Captação', false)
            ->assertDontSee('side-nav-title', false)
            ->assertDontSee('Abrir captação do dia', false);
    }

    public function test_entrar_em_modulo_grava_sessao_e_redireciona(): void
    {
        $user = $this->userWithPermissions([
            Permissions::CAPTACAO_LOTE_VISUALIZAR,
        ]);

        $this->assignModulosToUser($user, [AppModulo::Captacao]);

        $response = $this->actingAs($user)->get(route('modulos.entrar', AppModulo::Captacao));

        $response->assertRedirect(route('admin.captacao.pedidos-por-loja.carteiras'));
        $this->assertSame(AppModulo::Captacao->value, session('app_modulo'));
    }

    public function test_modulo_operacional_oculta_sidebar_nas_telas_admin(): void
    {
        $user = $this->vendedorUser();

        $this->actingAs($user)
            ->get(route('modulos.entrar', AppModulo::Captacao))
            ->assertRedirect();

        $response = $this->actingAs($user)->get(route('admin.captacao.pedidos-por-loja.carteiras'));

        $response
            ->assertOk()
            ->assertSee('Módulos', false)
            ->assertSee('Criar Captação', false)
            ->assertSee('Captação por loja', false)
            ->assertDontSee('side-nav-title', false)
            ->assertDontSee('Abrir captação do dia', false);
    }

    public function test_vendedor_nao_ve_modulo_centralizador(): void
    {
        $user = $this->vendedorUser();

        $this->actingAs($user)
            ->get(route('modulos.index'))
            ->assertOk()
            ->assertSee('Captação')
            ->assertDontSee('Centralizador');
    }

    public function test_administrador_exibe_sidebar_apos_entrar_no_modulo(): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $user->assignRole(Role::findOrCreate(\App\Enums\Roles::ADMINISTRADOR->value, 'web'));

        $this->actingAs($user)
            ->get(route('modulos.entrar', AppModulo::Administrador))
            ->assertRedirect(route('dashboard'));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('side-nav-title', false)
            ->assertSee(route('modulos.index'), false)
            ->assertSee('Módulos', false);
    }

    public function test_usuario_sem_modulos_ve_mensagem_no_hub(): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('modulos.index'))
            ->assertOk()
            ->assertSee('Nenhum módulo foi liberado');
    }
}
