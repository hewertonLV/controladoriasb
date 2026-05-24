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
}
