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
            'is_hub' => false,
            'is_unidade_producao' => false,
            'is_galpao_operacional' => false,
            'emite_nota_fiscal' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function galpaoOperacional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_galpao_operacional' => true,
            'emite_nota_fiscal' => false,
            'possui_estoque' => true,
            'is_hub' => false,
        ]);
    }

    /** Galpão regional que também emite NF (ex.: CD Barbalha). */
    public function galpaoComFaturamento(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_galpao_operacional' => true,
            'emite_nota_fiscal' => true,
            'possui_estoque' => true,
            'is_hub' => false,
        ]);
    }
}
