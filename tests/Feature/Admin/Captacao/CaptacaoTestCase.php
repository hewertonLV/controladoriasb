<?php

namespace Tests\Feature\Admin\Captacao;

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRota;
use App\Services\Captacao\CaptacaoLoteService;
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
     * @return array{faturamento: UnidadeNegocio, galpao: UnidadeNegocio, carteira: CaptacaoCarteira, cliente: Cliente, fruta: Fruta, rota: CaptacaoRota}
     */
    protected function cenarioCaptacaoBasico(): array
    {
        $faturamento = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'is_hub' => false,
            'is_galpao_operacional' => false,
            'emite_nota_fiscal' => true,
        ]);

        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();

        $carteira = app(CaptacaoLoteService::class)->garantirCarteira($faturamento->id, $galpao->id);

        $cliente = Cliente::factory()->create([
            'id_unidade_negocio' => $faturamento->id,
            'id_captacao_carteira' => $carteira->id,
            'razao_social' => 'CLIENTE CAPTACAO TESTE',
            'fantasia' => null,
        ]);

        $fruta = Fruta::factory()->create();

        $rota = CaptacaoRota::query()->create([
            'id_captacao_carteira' => $carteira->id,
            'nome' => 'Rota Teste',
            'ativo' => true,
        ]);

        app(ClienteFrutaVinculoService::class)->sincronizarFrutas($cliente, [$fruta->id]);

        return compact('faturamento', 'galpao', 'carteira', 'cliente', 'fruta', 'rota');
    }

    /**
     * @param  array{faturamento: UnidadeNegocio, galpao: UnidadeNegocio, carteira?: CaptacaoCarteira}  $c
     */
    protected function criarLoteCaptacao(array $c, string $data = '2026-05-29'): CaptacaoLote
    {
        $carteira = $c['carteira'] ?? app(CaptacaoLoteService::class)->garantirCarteira($c['faturamento']->id, $c['galpao']->id);

        return CaptacaoLote::query()->create([
            'data_referencia' => $data,
            'id_captacao_carteira' => $carteira->id,
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => 'CAPTACAO_EM_ANDAMENTO',
        ]);
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

    protected function concluirSaidaEstoqueFisicoPipeline(\App\Models\User $user, CaptacaoLote $lote): void
    {
        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-saida-estoque-fisico', $lote))
            ->assertRedirect();
    }

    protected function criarLoteComPedido(array $c, int $quantidade, ?string $precoVenda = null): \App\Models\Captacao\CaptacaoLote
    {
        $lote = $this->criarLoteCaptacao($c);

        $item = [
            'id_fruta' => $c['fruta']->id,
            'quantidade' => $quantidade,
        ];
        if ($precoVenda !== null) {
            $item['preco_venda'] = $precoVenda;
        }

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [$item],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $lote->update(['status' => \App\Enums\CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        return $lote->fresh();
    }

    protected function enviarNfVendaPipeline(\App\Models\User $user, CaptacaoLote $lote): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.nf-venda-cigan.upload', $lote), [
                'arquivo_nf_venda' => \Illuminate\Http\UploadedFile::fake()->create('nf-venda.xml', 50, 'application/xml'),
            ])
            ->assertRedirect();
    }

    protected function concluirRotasECarregamentoPipeline(
        \App\Models\User $user,
        \App\Models\Captacao\CaptacaoLote $lote,
        array $c,
        int $ordemCarregamento = 1,
    ): void {
        $pedido = \App\Models\Captacao\Pedido::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_cliente', $c['cliente']->id)
            ->first();

        if ($pedido !== null && $pedido->id_captacao_rota === null) {
            $this->actingAs($user)
                ->patchJson(route('admin.captacao.lotes.pedidos.rota', [$lote, $c['cliente']]), [
                    'id_captacao_rota' => $c['rota']->id,
                ])
                ->assertOk();
        }

        if ($pedido !== null) {
            $this->actingAs($user)
                ->patchJson(route('admin.captacao.lotes.pedidos.ordem-carregamento', [$lote, $c['cliente']]), [
                    'ordem_carregamento' => $ordemCarregamento,
                ])
                ->assertOk();
        }

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-vinculo-rotas', $lote))
            ->assertRedirect();
    }

    protected function concluirFreteVendaPipeline(\App\Models\User $user, \App\Models\Captacao\CaptacaoLote $lote): void
    {
        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-frete-venda', $lote))
            ->assertRedirect();
    }

    protected function concluirPipelineAteVendasFinalizadas(
        \App\Models\User $user,
        \App\Models\Captacao\CaptacaoLote $lote,
        array $c,
        int $ordemCarregamento = 1,
    ): void {
        $this->concluirRotasECarregamentoPipeline($user, $lote, $c, $ordemCarregamento);
        $this->concluirFreteVendaPipeline($user, $lote->fresh());
    }
}
