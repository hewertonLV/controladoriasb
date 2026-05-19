<?php

namespace Database\Factories;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Models\Estado;
use App\Models\Fruta;
use App\Services\Frutas\FrutaIcmsSyncService;
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
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Fruta $fruta): void {
            app(FrutaIcmsSyncService::class)->sync($fruta, [
                Estado::ID_CEARA => [
                    'entrada_externo' => '1.00',
                    'entrada_um_externo' => FrutaUmIcms::KG->value,
                    'entrada_nacional' => '2.00',
                    'entrada_um_nacional' => FrutaUmIcms::KG->value,
                    'saida_importada' => '5.00',
                    'saida_um_importada' => FrutaUmIcms::KG->value,
                    'saida_nacional' => '12.00',
                    'saida_um_nacional' => FrutaUmIcms::KG->value,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $icmsCeara
     */
    public function comIcmsCeara(array $icmsCeara = []): static
    {
        return $this->afterCreating(function (Fruta $fruta) use ($icmsCeara): void {
            app(FrutaIcmsSyncService::class)->sync($fruta, [
                Estado::ID_CEARA => array_replace([
                    'entrada_externo' => '1.00',
                    'entrada_um_externo' => FrutaUmIcms::KG->value,
                    'entrada_nacional' => '2.00',
                    'entrada_um_nacional' => FrutaUmIcms::KG->value,
                    'saida_importada' => '5.00',
                    'saida_um_importada' => FrutaUmIcms::KG->value,
                    'saida_nacional' => '12.00',
                    'saida_um_nacional' => FrutaUmIcms::KG->value,
                ], $icmsCeara),
            ]);
        });
    }
}
