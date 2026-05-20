<?php

namespace Tests\Feature\Errors;

use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    public function test_invalid_url_shows_custom_404_page_for_guest(): void
    {
        $response = $this->get('/url-invalida-que-nao-existe');

        $response
            ->assertNotFound()
            ->assertSee('Página não encontrada', false)
            ->assertSee('404', false)
            ->assertSee('Ir para o login', false);
    }
}
