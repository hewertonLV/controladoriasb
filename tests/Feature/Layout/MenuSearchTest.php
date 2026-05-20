<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_layout_includes_menu_search_modal_and_script(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="search-modal-input"', false)
            ->assertSee('Filtrar menu', false)
            ->assertSee('menu-search.js', false)
            ->assertSee('id="search-modal-results"', false);
    }
}
