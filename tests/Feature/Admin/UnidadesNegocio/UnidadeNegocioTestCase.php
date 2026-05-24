<?php

namespace Tests\Feature\Admin\UnidadesNegocio;

use App\Models\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class UnidadeNegocioTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{id_cigam: string, id_estado: int, razao_social: string, nome: string, cpf_cnpj: string|null, custo_operacional: string, possui_estoque: bool}
     */
    protected function unidadePayload(array $overrides = []): array
    {
        /** @var array{id_cigam: string, id_estado: int, razao_social: string, nome: string, cpf_cnpj: string|null, custo_operacional: string, possui_estoque: bool} */
        return array_replace([
            'id_cigam' => '009001',
            'id_estado' => Estado::ID_CEARA,
            'razao_social' => 'Unidade de Negócio Teste',
            'nome' => 'Unidade de Negócio Teste',
            'cpf_cnpj' => '11222333000181',
            'custo_operacional' => '10.50',
            'possui_estoque' => false,
            'is_unidade_producao' => false,
            'is_hub' => false,
            'is_galpao_operacional' => false,
            'emite_nota_fiscal' => true,
        ], $overrides);
    }
}
