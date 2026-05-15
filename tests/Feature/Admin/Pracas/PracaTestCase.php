<?php

namespace Tests\Feature\Admin\Pracas;

use App\Models\UnidadeNegocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class PracaTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{nome: string, id_unidade_negocio: int}
     */
    protected function pracaPayload(array $overrides = []): array
    {
        $unidadeId = $overrides['id_unidade_negocio'] ?? UnidadeNegocio::factory()->create()->id;

        /** @var array{nome: string, id_unidade_negocio: int} */
        return array_replace([
            'nome' => 'Praça Central',
            'id_unidade_negocio' => $unidadeId,
        ], $overrides);
    }
}
