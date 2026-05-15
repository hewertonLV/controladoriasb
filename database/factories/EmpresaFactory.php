<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cria vínculos em `empresas` sem disparar observers das entidades (evita duplicidade).
 *
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        $id = null;
        Model::withoutEvents(static function () use (&$id): void {
            $id = Cliente::factory()->create()->id;
        });

        return [
            'entidade_type' => Cliente::class,
            'entidade_id' => $id,
        ];
    }

    public function fornecedor(): static
    {
        return $this->state(function (): array {
            $id = null;
            Model::withoutEvents(static function () use (&$id): void {
                $id = Fornecedor::factory()->create()->id;
            });

            return [
                'entidade_type' => Fornecedor::class,
                'entidade_id' => $id,
            ];
        });
    }

    public function unidadeNegocio(): static
    {
        return $this->state(function (): array {
            $id = null;
            Model::withoutEvents(static function () use (&$id): void {
                $id = UnidadeNegocio::factory()->create()->id;
            });

            return [
                'entidade_type' => UnidadeNegocio::class,
                'entidade_id' => $id,
            ];
        });
    }

    public function unidadeNegocioInativa(): static
    {
        return $this->state(function (): array {
            $id = null;
            Model::withoutEvents(static function () use (&$id): void {
                $id = UnidadeNegocio::factory()->create(['status' => false])->id;
            });

            return [
                'entidade_type' => UnidadeNegocio::class,
                'entidade_id' => $id,
            ];
        });
    }
}
