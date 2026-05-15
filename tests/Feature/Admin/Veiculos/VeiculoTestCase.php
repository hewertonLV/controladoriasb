<?php

namespace Tests\Feature\Admin\Veiculos;

use App\Models\UnidadeNegocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class VeiculoTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{id_sbs: string, nome: string, tipo: string, id_unidade_negocio: int, status: string}
     */
    protected function veiculoPayload(array $overrides = []): array
    {
        $unit = UnidadeNegocio::factory()->create();

        /** @var array{id_sbs: string, nome: string, tipo: string, id_unidade_negocio: int, status: string} */
        return array_replace([
            'id_sbs' => '123',
            'nome' => 'Veículo Teste',
            'tipo' => 'CAMINHÃO',
            'id_unidade_negocio' => $unit->id,
            'status' => 'ATIVO',
        ], $overrides);
    }
}
