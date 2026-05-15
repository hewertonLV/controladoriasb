<?php

namespace Database\Factories;

use App\Models\Praca;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Praca>
 */
class PracaFactory extends Factory
{
    protected $model = Praca::class;

    public function definition(): array
    {
        return [
            'nome' => mb_strtoupper($this->faker->unique()->words(2, true), 'UTF-8'),
            'id_unidade_negocio' => UnidadeNegocio::factory(),
        ];
    }
}
