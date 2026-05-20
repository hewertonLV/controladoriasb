<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaProcedencia;
use App\Enums\FrutaUnidadeMedicao;
use App\Models\Estado;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class FrutaTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function frutaPayload(array $overrides = []): array
    {
        return array_replace([
            'id_cigam' => '42',
            'nome' => 'Banana Prata',
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '18.50',
            'procedencia' => FrutaProcedencia::NACIONAL->value,
            'icms' => [
                Estado::ID_CEARA => FrutaIcmsLinhaFormulario::vazia(),
            ],
        ], $overrides);
    }
}
