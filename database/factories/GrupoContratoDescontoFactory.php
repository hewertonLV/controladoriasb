<?php

namespace Database\Factories;

use App\Models\GrupoContrato;
use App\Models\GrupoContratoDesconto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoContratoDesconto>
 */
class GrupoContratoDescontoFactory extends Factory
{
    protected $model = GrupoContratoDesconto::class;

    public function definition(): array
    {
        return [
            'grupo_contrato_id' => GrupoContrato::factory(),
            'competencia' => '2026-01',
            'valor' => '0.00',
            'valor_teto' => null,
            'observacao' => null,
        ];
    }
}
