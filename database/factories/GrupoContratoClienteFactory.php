<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\GrupoContrato;
use App\Models\GrupoContratoCliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoContratoCliente>
 */
class GrupoContratoClienteFactory extends Factory
{
    protected $model = GrupoContratoCliente::class;

    public function definition(): array
    {
        return [
            'grupo_contrato_id' => GrupoContrato::factory(),
            'cliente_id' => Cliente::factory(),
            'competencia_inicio' => '2026-01',
            'competencia_fim' => null,
        ];
    }
}
