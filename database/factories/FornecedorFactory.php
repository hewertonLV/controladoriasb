<?php

namespace Database\Factories;

use App\Models\Estado;
use App\Models\Fornecedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fornecedor>
 */
class FornecedorFactory extends Factory
{
    protected $model = Fornecedor::class;

    public function definition(): array
    {
        $cnpjCpf = $this->faker->boolean(60)
            ? $this->faker->numerify(str_repeat('#', 14))
            : $this->faker->numerify(str_repeat('#', 11));

        return [
            'id_cigam' => str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'id_estado' => Estado::ID_CEARA,
            'razao_social' => $this->faker->company(),
            'fantasia' => $this->faker->optional(0.55)->company(),
            'cnpj_cpf' => $cnpjCpf,
        ];
    }
}
