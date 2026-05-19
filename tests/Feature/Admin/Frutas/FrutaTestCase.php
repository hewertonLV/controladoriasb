<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Models\Estado;
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
            'icms' => [
                Estado::ID_CEARA => [
                    'entrada_nacional' => '0.00',
                    'entrada_um_nacional' => FrutaUmIcms::KG->value,
                    'entrada_externo' => '0.00',
                    'entrada_um_externo' => FrutaUmIcms::KG->value,
                    'saida_importada' => '0.00',
                    'saida_um_importada' => FrutaUmIcms::KG->value,
                    'saida_nacional' => '0.00',
                    'saida_um_nacional' => FrutaUmIcms::KG->value,
                ],
            ],
        ], $overrides);
    }
}
