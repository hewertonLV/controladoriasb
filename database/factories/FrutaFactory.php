<?php

namespace Database\Factories;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Models\Fruta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fruta>
 */
class FrutaFactory extends Factory
{
    protected $model = Fruta::class;

    public function definition(): array
    {
        return [
            'id_cigam' => str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'nome' => mb_strtoupper($this->faker->unique()->words(2, true), 'UTF-8'),
            'unidade_medicao' => $this->faker->randomElement(FrutaUnidadeMedicao::values()),
            'kg_por_unidade_medicao' => $this->faker->randomFloat(2, 1, 50),
            'icms_ex_compra' => $this->faker->randomFloat(2, 0, 100),
            'icms_na_compra' => $this->faker->randomFloat(2, 0, 100),
            'um_icms' => $this->faker->randomElement(FrutaUmIcms::values()),
            'icms_venda' => $this->faker->randomFloat(2, 0, 25),
        ];
    }
}
