<?php

namespace Database\Factories;

use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estoque>
 */
class EstoqueFactory extends Factory
{
    protected $model = Estoque::class;

    public function definition(): array
    {
        return [
            'id_unidade_negocio' => UnidadeNegocio::factory()->state(['possui_estoque' => true]),
            'id_fruta' => Fruta::factory(),
            'qtd_fruta_kg' => 0,
            'qtd_fruta_um' => 0,
            'preco_medio_kg' => 0,
            'preco_medio_um' => 0,
            'valor_total_acumulado' => 0,
        ];
    }
}
