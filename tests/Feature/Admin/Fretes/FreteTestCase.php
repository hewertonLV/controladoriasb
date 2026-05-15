<?php

namespace Tests\Feature\Admin\Fretes;

use App\Enums\FreteStatusSituacao;
use App\Models\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class FreteTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{nome: string, valor: string, id_veiculo: int, descricao: string|null, status_situacao: string, valor_fruta_kg: string}
     */
    protected function fretePayload(array $overrides = []): array
    {
        $veiculo = Veiculo::factory()->create();

        /** @var array{nome: string, valor: string, id_veiculo: int, descricao: string|null, status_situacao: string, valor_fruta_kg: string} */
        return array_replace([
            'nome' => 'Frete Teste',
            'valor' => '1500.50',
            'id_veiculo' => $veiculo->id,
            'descricao' => 'Descrição teste',
            'status_situacao' => FreteStatusSituacao::ABERTA->value,
            'valor_fruta_kg' => '2.75',
        ], $overrides);
    }
}
