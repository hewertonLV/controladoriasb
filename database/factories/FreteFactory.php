<?php

namespace Database\Factories;

use App\Enums\FreteStatusSituacao;
use App\Models\Frete;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Frete>
 */
class FreteFactory extends Factory
{
    protected $model = Frete::class;

    public function definition(): array
    {
        return [
            'nome' => mb_strtoupper($this->faker->unique()->words(3, true)),
            'valor' => $this->faker->randomFloat(2, 0, 5000),
            'id_veiculo' => Veiculo::factory(),
            'descricao' => $this->faker->optional()->sentence(),
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
            'valor_fruta_kg' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
