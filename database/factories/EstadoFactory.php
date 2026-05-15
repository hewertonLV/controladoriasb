<?php

namespace Database\Factories;

use App\Models\Estado;
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
            'nome' => mb_strtoupper($this->faker->unique()->lexify('EST????'), 'UTF-8'),
            'descricao' => $this->faker->optional()->sentence(),
        ];
    }
}
