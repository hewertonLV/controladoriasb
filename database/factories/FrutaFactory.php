<?php

namespace Database\Factories;

use App\Enums\FrutaProcedencia;
use App\Enums\FrutaUnidadeMedicao;
use App\Models\Estado;
use App\Models\Fruta;
use App\Services\Frutas\FrutaIcmsSyncService;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
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
            'procedencia' => FrutaProcedencia::NACIONAL->value,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Fruta $fruta): void {
            if ($fruta->icmsAliquotas()->exists()) {
                return;
            }

            app(FrutaIcmsSyncService::class)->sync($fruta, [
                Estado::ID_CEARA => [
                    FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '2.00',
                    FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '1.00',
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '12.00',
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => '5.00',
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => '10.00',
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => '4.00',
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
                    FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '2.00',
                    FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '1.00',
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '12.00',
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => '5.00',
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => '10.00',
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => '4.00',
                ], $icmsCeara),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $icmsPe
     */
    public function comIcmsPernambuco(array $icmsPe = []): static
    {
        return $this->afterCreating(function (Fruta $fruta) use ($icmsPe): void {
            app(FrutaIcmsSyncService::class)->sync($fruta, [
                Estado::ID_PERNAMBUCO => array_replace([
                    FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '0.00',
                    FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '0.00',
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '20.50',
                    FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => '12.00',
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => '18.00',
                    FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => '10.00',
                ], $icmsPe),
            ]);
        });
    }

    public function internacional(): static
    {
        return $this->state(fn () => [
            'procedencia' => FrutaProcedencia::INTERNACIONAL->value,
        ]);
    }
}
