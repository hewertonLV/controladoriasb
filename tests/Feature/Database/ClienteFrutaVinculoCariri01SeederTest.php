<?php

namespace Tests\Feature\Database;

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\ClienteFrutaVinculo;
use App\Models\Cliente;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use Database\Seeders\ClienteFrutaVinculoCariri01Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteFrutaVinculoCariri01SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_propaga_frutas_do_cliente_origem_para_demais_lojas_da_carteira(): void
    {
        $faturamento = UnidadeNegocio::factory()->create(['emite_nota_fiscal' => true]);
        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();

        $carteira = CaptacaoCarteira::query()->create([
            'nome' => 'Cariri 01',
            'id_unidade_negocio_faturamento' => $faturamento->id,
            'id_unidade_negocio_galpao' => $galpao->id,
            'ativo' => true,
        ]);

        $frutaA = Fruta::factory()->create();
        $frutaB = Fruta::factory()->create();
        $frutaExtra = Fruta::factory()->create();

        $origem = Cliente::factory()->create([
            'id_cigam' => '005134',
            'razao_social' => 'ALIMENTOS SOFRIOS',
            'id_unidade_negocio' => $faturamento->id,
            'id_captacao_carteira' => $carteira->id,
        ]);

        foreach ([$frutaA->id, $frutaB->id] as $idFruta) {
            ClienteFrutaVinculo::query()->create([
                'id_cliente' => $origem->id,
                'id_fruta' => $idFruta,
                'ativo' => true,
            ]);
        }

        $destinoSemVinculo = Cliente::factory()->create([
            'id_cigam' => '009999',
            'id_unidade_negocio' => $faturamento->id,
            'id_captacao_carteira' => $carteira->id,
        ]);

        $destinoComUmaFruta = Cliente::factory()->create([
            'id_cigam' => '008888',
            'id_unidade_negocio' => $faturamento->id,
            'id_captacao_carteira' => $carteira->id,
        ]);
        ClienteFrutaVinculo::query()->create([
            'id_cliente' => $destinoComUmaFruta->id,
            'id_fruta' => $frutaExtra->id,
            'ativo' => true,
        ]);

        $foraDaCarteira = Cliente::factory()->create([
            'id_cigam' => '007777',
            'id_unidade_negocio' => $faturamento->id,
            'id_captacao_carteira' => null,
        ]);

        $this->seed(ClienteFrutaVinculoCariri01Seeder::class);

        foreach ([$destinoSemVinculo, $destinoComUmaFruta] as $destino) {
            $this->assertTrue(
                ClienteFrutaVinculo::query()
                    ->where('id_cliente', $destino->id)
                    ->where('ativo', true)
                    ->whereIn('id_fruta', [$frutaA->id, $frutaB->id])
                    ->count() === 2,
            );
        }

        $this->assertTrue(
            ClienteFrutaVinculo::query()
                ->where('id_cliente', $destinoComUmaFruta->id)
                ->where('ativo', true)
                ->where('id_fruta', $frutaExtra->id)
                ->exists(),
        );

        $this->assertFalse(
            ClienteFrutaVinculo::query()
                ->where('id_cliente', $foraDaCarteira->id)
                ->where('ativo', true)
                ->exists(),
        );
    }
}
