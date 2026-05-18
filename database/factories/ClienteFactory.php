<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        $id = (string) $this->faker->unique()->numberBetween(1, 999999);

        $cnpjCpf = $this->faker->boolean(60)
            ? $this->faker->numerify(str_repeat('#', 11))
            : $this->faker->numerify(str_repeat('#', 14));

        return [
            'id_cigam' => $id,
            'razao_social' => $this->faker->company(),
            'fantasia' => $this->faker->optional()->company(),
            'cnpj_cpf' => $cnpjCpf,
            'id_unidade_negocio' => UnidadeNegocio::factory(),
            'grupo_id' => null,
            'desconto_nf' => '0.00',
        ];
    }

    public function comGrupo(): static
    {
        return $this->state(fn () => [
            'grupo_id' => Grupo::factory(),
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Cliente $cliente): void {
            if ($cliente->id_praca !== null) {
                return;
            }

            $unidadeId = (int) $cliente->id_unidade_negocio;
            if ($unidadeId < 1) {
                return;
            }

            $praca = Praca::factory()->create([
                'id_unidade_negocio' => $unidadeId,
            ]);

            $cliente->id_praca = $praca->id;
        });
    }
}
