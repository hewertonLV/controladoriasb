<?php

namespace Tests\Feature\Admin\Clientes;

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Grupo;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CaptacaoLoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class ClienteTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function clientePayload(array $overrides = []): array
    {
        $unit = $overrides['id_unidade_negocio'] ?? null;
        if (! is_int($unit)) {
            $unitModel = UnidadeNegocio::factory()->create();
            $unit = $unitModel->id;
        }

        $pracaId = $overrides['id_praca'] ?? null;
        if (! is_int($pracaId)) {
            $praca = Praca::factory()->create(['id_unidade_negocio' => $unit]);
            $pracaId = $praca->id;
        }

        $payload = array_replace([
            'id_cigam' => '1',
            'numero_divisao' => '10',
            'razao_social' => 'Cliente Teste LTDA',
            'fantasia' => null,
            'cnpj_cpf' => '52998224725',
            'id_unidade_negocio' => $unit,
            'id_praca' => $pracaId,
            'grupo_id' => null,
            'desconto_nf' => '10.00',
            'id_unidade_negocio_saida_fisico_padrao' => null,
        ], $overrides);

        if (
            ! array_key_exists('id_unidade_negocio_saida_fisico_padrao', $overrides)
            || $overrides['id_unidade_negocio_saida_fisico_padrao'] === null
        ) {
            $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();
            app(CaptacaoLoteService::class)->garantirCarteira((int) $unit, $galpao->id);
            $payload['id_unidade_negocio_saida_fisico_padrao'] ??= $galpao->id;
        }

        return $payload;
    }

    protected function criarGrupo(string $nome = 'GRUPO TESTE'): Grupo
    {
        return Grupo::factory()->create(['nome' => $nome]);
    }
}
