<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_persist_theme_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('theme-settings.update'), [
                'data-bs-theme' => 'dark',
                'data-layout-mode' => 'detached',
                'data-topbar-color' => 'brand',
                'data-menu-color' => 'dark',
                'data-sidenav-size' => 'compact',
            ])
            ->assertOk()
            ->assertJsonPath('theme_settings.data-layout-mode', 'detached');

        $this->assertSame('detached', $user->refresh()->theme_settings['data-layout-mode']);
        $this->assertSame('dark', $user->theme_settings['data-bs-theme']);
    }

    public function test_theme_settings_are_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->postJson(route('theme-settings.update'), [
                'data-layout-mode' => 'detached',
            ])
            ->assertOk();

        $this->assertSame('detached', $userA->refresh()->themeSettings()['data-layout-mode']);
        $this->assertSame('fluid', $userB->refresh()->themeSettings()['data-layout-mode']);
    }

    public function test_layout_renders_saved_theme_settings_before_assets_run(): void
    {
        $user = User::factory()->create([
            'theme_settings' => [
                'data-bs-theme' => 'dark',
                'data-layout-mode' => 'detached',
                'data-topbar-color' => 'brand',
                'data-menu-color' => 'dark',
                'data-sidenav-size' => 'compact',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-bs-theme="dark"', false)
            ->assertSee('data-layout-mode="detached"', false)
            ->assertSee('data-topbar-color="brand"', false)
            ->assertSee('data-menu-color="dark"', false)
            ->assertSee('data-sidenav-size="compact"', false)
            ->assertSee('data-theme-settings-source="server"', false);
    }

    public function test_guest_cannot_update_theme_settings(): void
    {
        $this->postJson(route('theme-settings.update'), [
            'data-layout-mode' => 'detached',
        ])->assertUnauthorized();
    }
}
