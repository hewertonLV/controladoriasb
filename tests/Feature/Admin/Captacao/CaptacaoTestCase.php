<?php

namespace Tests\Feature\Admin\Captacao;

use App\Models\Captacao\CaptacaoRota;
use App\Models\Cliente;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\MovimentacaoEstoque;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\ClienteFrutaVinculoService;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

abstract class CaptacaoTestCase extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    /**
     * @return array{faturamento: UnidadeNegocio, galpao: UnidadeNegocio, cliente: Cliente, fruta: Fruta, rota: CaptacaoRota}
     */
    protected function cenarioCaptacaoBasico(): array
    {
        $faturamento = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'is_hub' => false,
            'is_galpao_operacional' => false,
        ]);

        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();

        $cliente = Cliente::factory()->create([
            'id_unidade_negocio' => $faturamento->id,
        ]);

        $fruta = Fruta::factory()->create();

        $rota = CaptacaoRota::query()->create([
            'id_unidade_negocio_galpao' => $galpao->id,
            'nome' => 'Rota Teste',
            'ativo' => true,
        ]);

        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($cliente, [$fruta->id]);

        return compact('faturamento', 'galpao', 'cliente', 'fruta', 'rota');
    }

    protected function seedCaptacaoMovimentacao(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
        ]);
    }

    protected function criarHubComEstoque(Fruta $fruta, string $qtdKg = '100.00', string $qtdUm = '10.00'): UnidadeNegocio
    {
        $fruta->update(['kg_por_unidade_medicao' => '10.00']);

        $hub = UnidadeNegocio::factory()->create([
            'is_hub' => true,
            'possui_estoque' => true,
        ]);

        $estoque = Estoque::factory()->create([
            'id_unidade_negocio' => $hub->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => $qtdKg,
            'qtd_fruta_um' => $qtdUm,
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $hub->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => $qtdKg,
            'qtd_fruta_um' => $qtdUm,
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        return $hub;
    }

    protected function criarCoGalpao(UnidadeNegocio $galpao): void
    {
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $galpao->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);
    }
}
