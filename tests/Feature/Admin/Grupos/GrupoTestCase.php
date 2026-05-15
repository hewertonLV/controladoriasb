<?php

namespace Tests\Feature\Admin\Grupos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class GrupoTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{nome: string}
     */
    protected function grupoPayload(array $overrides = []): array
    {
        /** @var array{nome: string} */
        return array_replace([
            'nome' => 'Grupo Teste',
        ], $overrides);
    }
}
