<?php

namespace Database\Factories;

use App\Models\UnidadeNegocio;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veiculo>
 */
class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    public function definition(): array
    {
        return [
            'id_sbs' => $this->faker->unique()->numberBetween(1, 2000000000),
            'nome' => $this->faker->company(),
            'tipo' => $this->faker->word(),
            'id_unidade_negocio' => UnidadeNegocio::factory(),
            'status' => 'ATIVO',
        ];
    }
}
