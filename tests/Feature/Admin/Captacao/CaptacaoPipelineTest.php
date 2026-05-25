<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;

class CaptacaoPipelineTest extends CaptacaoTestCase
{
    public function test_fluxo_lucas_jefferson_transiciona_status(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan,
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::TransferenciaCiganIniciada, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.validar-transferencias', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::AguardandoVinculoFrete, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.concluir-frete', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::TransferenciaFinalizada, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::FaturamentoCiganIniciado, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.pipeline.finalizar-vendas', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::VendasFinalizadas, $lote->status);
    }

    public function test_finalizar_vendas_bloqueia_pedido_com_quantidade_sem_rota(): void
    {
        $this->seedCaptacaoMovimentacao();

        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta'], '200.00', '20.00');
        $this->criarCoGalpao($c['galpao']);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::FaturamentoCiganIniciado,
        ]);

        $pedido = \App\Models\Captacao\Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => null,
            'captacao_concluida' => true,
            'origem' => \App\Enums\PedidoOrigem::Web,
        ]);

        \App\Models\Captacao\PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => '12.50',
            'version' => 1,
        ]);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)
            ->from(route('admin.captacao.lotes.show', $lote))
            ->post(route('admin.captacao.lotes.pipeline.finalizar-vendas', $lote))
            ->assertRedirect(route('admin.captacao.lotes.show', $lote))
            ->assertSessionHasErrors('pedidos');

        $this->assertSame(CaptacaoLoteStatus::FaturamentoCiganIniciado, $lote->fresh()->status);
    }

    public function test_matriz_exibe_aba_arquivo_cigan_na_transferencia_iniciada(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertOk()
            ->assertSee('Arquivo Cigan', false)
            ->assertSee('matriz-tab-arquivo-cigan', false)
            ->assertSee('Baixar arquivo TXT (Cigan)', false);
    }

    public function test_download_arquivo_cigan_transferencia_txt_vazio(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $response = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertSame('', $response->streamedContent());
        $this->assertStringContainsString(
            'cigan-transferencia-lote-'.$lote->id.'.txt',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_download_arquivo_cigan_transferencia_indisponivel_fora_da_fase(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote))
            ->assertNotFound();
    }
}
