<?php

namespace Database\Factories;

use App\Models\GrupoContrato;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoContrato>
 */
class GrupoContratoFactory extends Factory
{
    protected $model = GrupoContrato::class;

    public function definition(): array
    {
        return [
            'nome' => 'CONTRATO '.$this->faker->unique()->word(),
            'descricao' => $this->faker->optional()->sentence(),
            'ativo' => true,
        ];
    }
}
