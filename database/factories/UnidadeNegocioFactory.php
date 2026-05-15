<?php

namespace Database\Factories;

use App\Models\Estado;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnidadeNegocio>
 */
class UnidadeNegocioFactory extends Factory
{
    protected $model = UnidadeNegocio::class;

    public function definition(): array
    {
        $razaoSocial = $this->faker->company();

        return [
            'id_cigam' => str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'id_estado' => Estado::ID_CEARA,
            'razao_social' => $razaoSocial,
            'nome' => $razaoSocial,
            'cpf_cnpj' => $this->faker->numerify(str_repeat('#', 14)),
            'custo_operacional' => $this->faker->randomFloat(2, 0, 5000),
            'status' => true,
            'possui_estoque' => false,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
