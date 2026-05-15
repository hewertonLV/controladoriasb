<?php

namespace Database\Factories;

use App\Models\StatusMovimentacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusMovimentacao>
 */
class StatusMovimentacaoFactory extends Factory
{
    protected $model = StatusMovimentacao::class;

    public function definition(): array
    {
        return [
            'nome' => mb_strtoupper($this->faker->unique()->lexify('STATUS????'), 'UTF-8'),
        ];
    }
}
