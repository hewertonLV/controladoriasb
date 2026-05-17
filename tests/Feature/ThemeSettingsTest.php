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
        $this->assertSame('detached', session('theme_settings.data-layout-mode'));
        $this->assertSame($user->getKey(), session('theme_settings_user_id'));
    }

    public function test_theme_settings_are_scoped_per_user(): void
    {
        $userA = User::factory()->create([
            'theme_settings' => [
                'data-layout-mode' => 'detached',
            ],
        ]);
        $userB = User::factory()->create([
            'theme_settings' => [
                'data-layout-mode' => 'fluid',
            ],
        ]);

        $this->actingAs($userA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-layout-mode="detached"', false);

        $this->actingAs($userB)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-layout-mode="fluid"', false);
    }

    public function test_layout_uses_session_theme_settings_before_database_settings(): void
    {
        $user = User::factory()->create([
            'theme_settings' => [
                'data-layout-mode' => 'fluid',
            ],
        ]);

        $this->actingAs($user)
            ->withSession([
                'theme_settings_user_id' => $user->getKey(),
                'theme_settings' => array_merge(User::defaultThemeSettings(), [
                    'data-layout-mode' => 'detached',
                ]),
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-layout-mode="detached"', false)
            ->assertSee('window.themeSettingsFromServer', false)
            ->assertSee('"data-layout-mode":"detached"', false)
            ->assertSee('data-theme-settings-source="server"', false);
    }

    public function test_middleware_loads_theme_settings_into_session_when_missing(): void
    {
        $user = User::factory()->create([
            'theme_settings' => [
                'data-layout-mode' => 'detached',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas('theme_settings.data-layout-mode', 'detached')
            ->assertSessionHas('theme_settings_user_id', $user->getKey())
            ->assertSee('data-layout-mode="detached"', false);
    }

    public function test_guest_layout_uses_default_theme_settings(): void
    {
        $this->view('dashboard')
            ->assertSee('data-bs-theme="light"', false)
            ->assertSee('data-layout-mode="fluid"', false)
            ->assertSee('data-theme-settings-source="guest"', false);
    }

    public function test_guest_cannot_update_theme_settings(): void
    {
        $this->postJson(route('theme-settings.update'), [
            'data-layout-mode' => 'detached',
        ])->assertUnauthorized();
    }

    public function test_config_script_does_not_overwrite_rendered_layout_mode(): void
    {
        $configScript = file_get_contents(public_path('assets/js/config.js'));
        $appScript = file_get_contents(public_path('assets/js/app.js'));
        $persistenceScript = file_get_contents(public_path('assets/js/theme-settings-persistence.js'));

        $this->assertStringNotContainsString("setAttribute('data-layout-mode'", $configScript);
        $this->assertStringNotContainsString('setAttribute("data-layout-mode"', $configScript);
        $this->assertStringNotContainsString('changeLayoutMode("default",!1)', $appScript);
        $this->assertStringNotContainsString('changeLeftbarSize("full",!1)', $appScript);
        $this->assertStringContainsString('data-layout-responsive', $appScript);
        $this->assertStringContainsString('protectedThemeAttributes', $persistenceScript);
        $this->assertStringContainsString('const applyThemeSettings', $persistenceScript);
        $this->assertStringContainsString('function syncThemeControls(settings)', $persistenceScript);
        $this->assertStringContainsString('querySelectorAll(`input[name="${name}"]`)', $persistenceScript);
        $this->assertStringContainsString('window.applyThemeSettings = applyThemeSettings', $persistenceScript);
        $this->assertStringContainsString('window.syncThemeControls = syncThemeControls', $persistenceScript);
        $this->assertStringContainsString('input.checked = isSelected', $persistenceScript);
        $this->assertStringContainsString("card.classList.toggle('active', isSelected)", $persistenceScript);
        $this->assertStringContainsString('new MutationObserver', $persistenceScript);
        $this->assertStringContainsString('event.isTrusted', $persistenceScript);
        $this->assertStringContainsString('requestAnimationFrame', $persistenceScript);
    }
}
