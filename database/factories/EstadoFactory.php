<?php

namespace Database\Factories;

use App\Models\Estado;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estado>
 */
class EstadoFactory extends Factory
{
    protected $model = Estado::class;

    public function definition(): array
    {
        return [
            'id_cigam' => TextoCadastro::normalizarIdCigamAteSeisDigitos(
                (string) $this->faker->unique()->numberBetween(100, 999999),
            ),
            'nome' => mb_strtoupper($this->faker->unique()->lexify('EST????'), 'UTF-8'),
            'abreviacao' => mb_strtoupper($this->faker->unique()->lexify('??'), 'UTF-8'),
            'descricao' => $this->faker->optional()->sentence(),
        ];
    }
}
