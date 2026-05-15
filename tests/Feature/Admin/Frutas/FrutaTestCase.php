<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class FrutaTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{id_cigam: string, nome: string, unidade_medicao: string, kg_por_unidade_medicao: float|string, icms_ex_compra: string, icms_na_compra: string, um_icms: string, icms_venda: string}
     */
    protected function frutaPayload(array $overrides = []): array
    {
        /** @var array{id_cigam: string, nome: string, unidade_medicao: string, kg_por_unidade_medicao: float|string, icms_ex_compra: string, icms_na_compra: string, um_icms: string, icms_venda: string} */
        return array_replace([
            'id_cigam' => '42',
            'nome' => 'Banana Prata',
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '18.50',
            'icms_ex_compra' => '0.00',
            'icms_na_compra' => '0.00',
            'um_icms' => FrutaUmIcms::KG->value,
            'icms_venda' => '0.00',
        ], $overrides);
    }
}
