<?php

namespace Database\Factories;

use App\Models\HistoricoCOUnNg;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HistoricoCOUnNg>
 */
class HistoricoCOUnNgFactory extends Factory
{
    protected $model = HistoricoCOUnNg::class;

    public function definition(): array
    {
        return [
            'id_unidade_negocio' => UnidadeNegocio::factory(),
            'custo_operacional' => $this->faker->randomFloat(2, 0, 1000),
            'status_position' => true,
        ];
    }

    public function historico(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_position' => false,
        ]);
    }
}
