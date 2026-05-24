<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\PedidoOrigem;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Captacao\PedidoItem;

class FinalizarCaptacaoFaturamentoTest extends CaptacaoTestCase
{
    public function test_finalizar_captacao_redireciona_para_lote_e_atualiza_status(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'origem' => PedidoOrigem::Web,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'version' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.faturamento.finalizar'), [
                'data_referencia' => '2026-05-29',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_captacao_lote' => $lote->id,
            ])
            ->assertRedirect(route('admin.captacao.lotes.show', $lote))
            ->assertSessionHas('success');

        $this->assertSame(
            CaptacaoLoteStatus::AguardandoTransferenciaCigan,
            $lote->fresh()->status,
        );
    }

    public function test_finalizar_bloqueia_loja_com_quantidade_sem_rota_e_exibe_erro(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => null,
            'origem' => PedidoOrigem::Web,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '3.000',
            'version' => 1,
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.captacao.lotes.show', $lote))
            ->post(route('admin.captacao.faturamento.finalizar'), [
                'data_referencia' => '2026-05-29',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_captacao_lote' => $lote->id,
            ]);

        $response->assertRedirect(route('admin.captacao.lotes.show', $lote));
        $response->assertSessionHasErrors('pedidos');
        $this->assertSame(CaptacaoLoteStatus::CaptacaoEmAndamento, $lote->fresh()->status);
    }

    public function test_finalizar_permite_loja_na_matriz_sem_rota_se_sem_quantidade(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => 'CAPTACAO_PEDIDOS',
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => null,
            'origem' => PedidoOrigem::Web,
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.faturamento.finalizar'), [
                'data_referencia' => '2026-05-29',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_captacao_lote' => $lote->id,
            ])
            ->assertRedirect(route('admin.captacao.lotes.show', $lote))
            ->assertSessionHas('success');
    }
}
