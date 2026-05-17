<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'theme_settings' => [
                'data-layout-mode' => 'detached',
            ],
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('theme_settings.data-layout-mode', 'detached');
        $response->assertSessionHas('theme_settings_user_id', $user->getKey());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'theme_settings' => array_merge(User::defaultThemeSettings(), [
                    'data-layout-mode' => 'detached',
                ]),
            ])
            ->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionMissing('theme_settings');
        $response->assertSessionMissing('theme_settings_user_id');
    }
}
