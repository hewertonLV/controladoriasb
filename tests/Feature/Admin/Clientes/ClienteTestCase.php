<?php

namespace Tests\Feature\Admin\Clientes;

use App\Models\Grupo;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class ClienteTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function clientePayload(array $overrides = []): array
    {
        $unit = $overrides['id_unidade_negocio'] ?? null;
        if (! is_int($unit)) {
            $unitModel = UnidadeNegocio::factory()->create();
            $unit = $unitModel->id;
        }

        $pracaId = $overrides['id_praca'] ?? null;
        if (! is_int($pracaId)) {
            $praca = Praca::factory()->create(['id_unidade_negocio' => $unit]);
            $pracaId = $praca->id;
        }

        return array_replace([
            'id_cigam' => '1',
            'razao_social' => 'Cliente Teste LTDA',
            'cnpj_cpf' => '52998224725',
            'id_unidade_negocio' => $unit,
            'id_praca' => $pracaId,
            'grupo_id' => null,
            'desconto_nf' => '10.00',
            'desconto_contrato' => '5.00',
        ], $overrides);
    }

    protected function criarGrupo(string $nome = 'GRUPO TESTE'): Grupo
    {
        return Grupo::factory()->create(['nome' => $nome]);
    }
}
