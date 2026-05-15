<?php

namespace Database\Factories;

use App\Models\CategoriaMovimentacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaMovimentacao>
 */
class CategoriaMovimentacaoFactory extends Factory
{
    protected $model = CategoriaMovimentacao::class;

    public function definition(): array
    {
        return [
            'nome' => mb_strtoupper($this->faker->unique()->lexify('CATEGORIA????'), 'UTF-8'),
        ];
    }
}
