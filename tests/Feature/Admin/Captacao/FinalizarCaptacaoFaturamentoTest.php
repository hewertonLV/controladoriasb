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

        $lote = $this->criarLoteCaptacao($c);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'captacao_concluida' => true,
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

    public function test_finalizar_permite_loja_com_quantidade_sem_rota(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => null,
            'captacao_concluida' => true,
            'origem' => PedidoOrigem::Web,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '3.000',
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

    public function test_finalizar_permite_loja_na_matriz_sem_rota_se_sem_quantidade(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => null,
            'captacao_concluida' => true,
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

    public function test_finalizar_permite_loja_sem_pedido_no_dia(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $clienteSemPedido = \App\Models\Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'razao_social' => 'LOJA SEM PEDIDO HOJE',
        ]);
        app(\App\Services\Captacao\ClienteFrutaVinculoService::class)->sincronizarFrutas($clienteSemPedido, [$c['fruta']->id]);

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'captacao_concluida' => true,
            'origem' => PedidoOrigem::Web,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 4,
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
    }

    public function test_finalizar_reconcilia_lote_quando_dia_ja_finalizado(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c, '2026-05-25');

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $lote->id,
            'id_cliente' => $c['cliente']->id,
            'captacao_concluida' => true,
            'origem' => PedidoOrigem::Web,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 2,
            'version' => 1,
        ]);

        \App\Models\Captacao\CaptacaoFaturamentoDia::query()->create([
            'data_referencia' => '2026-05-25',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'status' => \App\Enums\CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada,
            'finalizado_em' => now(),
            'finalizado_por_user_id' => $user->id,
        ]);

        $this->assertSame(CaptacaoLoteStatus::CaptacaoEmAndamento, $lote->fresh()->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.faturamento.finalizar'), [
                'data_referencia' => '2026-05-25',
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

    public function test_finalizar_complementar_com_dia_ja_finalizado_atualiza_status_do_lote(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $loteAnterior = $this->criarLoteCaptacao($c, '2026-05-27');
        $loteAnterior->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $loteComplementar = $this->criarLoteCaptacao($c, '2026-05-27');

        $pedido = Pedido::query()->create([
            'id_captacao_lote' => $loteComplementar->id,
            'id_cliente' => $c['cliente']->id,
            'captacao_concluida' => true,
            'origem' => PedidoOrigem::Web,
        ]);

        PedidoItem::query()->create([
            'id_pedido' => $pedido->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 3,
            'version' => 1,
        ]);

        \App\Models\Captacao\CaptacaoFaturamentoDia::query()->create([
            'data_referencia' => '2026-05-27',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'status' => \App\Enums\CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada,
            'finalizado_em' => now(),
            'finalizado_por_user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.faturamento.finalizar'), [
                'data_referencia' => '2026-05-27',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
                'id_captacao_lote' => $loteComplementar->id,
            ])
            ->assertRedirect(route('admin.captacao.lotes.show', $loteComplementar))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $this->assertStringNotContainsString(
            'Lucas',
            session('success'),
        );

        $this->assertSame(
            CaptacaoLoteStatus::AguardandoTransferenciaCigan,
            $loteComplementar->fresh()->status,
        );
    }
}
