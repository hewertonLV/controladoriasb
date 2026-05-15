<?php

namespace Tests\Feature\Admin\Fornecedores;

use App\Models\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class FornecedorTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{id_cigam: string, id_estado: int, razao_social: string, fantasia: string|null, cnpj_cpf: string}
     */
    protected function fornecedorPayload(array $overrides = []): array
    {
        /** @var array{id_cigam: string, id_estado: int, razao_social: string, fantasia: string|null, cnpj_cpf: string} */
        return array_replace([
            'id_cigam' => '42',
            'id_estado' => Estado::ID_CEARA,
            'razao_social' => 'Fornecedor Teste Ltda',
            'fantasia' => 'Fornecedor Fantasia',
            'cnpj_cpf' => '11222333000181',
        ], $overrides);
    }
}
