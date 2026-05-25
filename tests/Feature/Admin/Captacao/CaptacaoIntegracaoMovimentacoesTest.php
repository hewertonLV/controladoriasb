<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\VendaNota;

class CaptacaoIntegracaoMovimentacoesTest extends CaptacaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCaptacaoMovimentacao();
    }

    public function test_validar_transferencias_cria_transferencia_hub_para_galpao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 10);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.validar-transferencias', $lote))
            ->assertRedirect();

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
            'id_fruta' => $c['fruta']->id,
        ]);

        $vinculo = CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->first();

        $this->assertNotNull($vinculo?->transferencia_origem_id);

        $saida = Movimentacao::query()
            ->where('transferencia_origem_id', $vinculo->transferencia_origem_id)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->first();

        $this->assertNotNull($saida);
        $empresaHub = $hub->registroCorporativo()->firstOrFail();
        $this->assertSame($empresaHub->id, (int) $saida->id_empresa_origem);
        $this->assertSame($c['galpao']->registroCorporativo()->firstOrFail()->id, (int) $saida->id_empresa_destino);
    }

    public function test_finalizar_vendas_cria_nota_por_cliente(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = $this->criarLoteComPedido($c, 5, '12.50');

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote));
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.validar-transferencias', $lote));
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.concluir-frete', $lote));
        $this->actingAs($user)->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote));

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.finalizar-vendas', $lote))
            ->assertRedirect();

        $this->assertDatabaseHas('captacao_lote_movimentacoes', [
            'id_captacao_lote' => $lote->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
        ]);

        $this->assertGreaterThan(0, VendaNota::query()->count());
    }

    public function test_matriz_estado_e_incremento_celula(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
            'id_cliente' => $c['cliente']->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.captacao.lotes.matriz.estado', $lote))
            ->assertOk()
            ->assertJsonStructure(['version', 'celulas']);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'incremento' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('item.quantidade', '2.000');

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
                'id_cliente' => $c['cliente']->id,
                'id_fruta' => $c['fruta']->id,
                'quantidade' => 2,
                'preco_venda' => '15.75',
            ])
            ->assertOk()
            ->assertJsonPath('item.preco_venda', '15.7500');
    }

    public function test_telas_romaneio_manual_e_frete(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::AguardandoVinculoFrete,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $this->actingAs($user)
            ->get(route('admin.captacao.romaneio-manual.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.fretes.index', $lote))
            ->assertOk();
    }

    public function test_api_contrato_publico(): void
    {
        $this->getJson(route('api.v1.captacao.contrato'))
            ->assertOk()
            ->assertJsonPath('adr', 'ADR-0081');
    }

    private function criarLoteComPedido(array $c, int $quantidade, ?string $precoVenda = null): CaptacaoLote
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

        $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        return $lote->fresh();
    }

}
