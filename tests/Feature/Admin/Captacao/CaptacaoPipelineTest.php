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

    public function test_download_arquivo_cigan_exige_hub_origem(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote))
            ->assertRedirect()
            ->assertSessionHasErrors('id_unidade_negocio_hub_origem');
    }

    public function test_download_arquivo_cigan_transferencia_txt_layout_edi(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $c['faturamento']->update(['id_cigam' => '881001']);
        $c['galpao']->update(['id_cigam' => '882002']);
        $hub = $this->criarHubComEstoque($c['fruta']);
        $hub->update(['id_cigam' => '883003']);
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $this->actingAs($user)->postJson(route('admin.captacao.lotes.matriz.adicionar-loja', $lote), [
            'id_cliente' => $c['cliente']->id,
        ])->assertOk();

        $this->actingAs($user)->patchJson(route('admin.captacao.lotes.celula.update', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'preco_venda' => 10,
        ])->assertOk();

        $lote->update([
            'status' => CaptacaoLoteStatus::TransferenciaCiganIniciada,
            'id_unidade_negocio_hub_origem' => $hub->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.arquivo-cigan-transferencia', $lote));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/plain; charset=ISO-8859-1');

        $conteudo = $response->streamedContent();
        $linhas = array_values(array_filter(explode("\n", rtrim($conteudo, "\r\n"))));

        $this->assertGreaterThanOrEqual(2, count($linhas));
        $this->assertSame(688, strlen($linhas[0]));
        $this->assertSame('N', $linhas[0][0]);
        $this->assertSame('881001', substr($linhas[0], 51, 6), 'Cliente/cobrança deve ser o faturamento da carteira');
        $this->assertSame('883003', substr($linhas[0], 131, 6), 'Transportadora deve ser o HUB de origem');
        $this->assertNotSame('882002', substr($linhas[0], 51, 6), 'Galpão não é o destino fiscal do registro N');
        $this->assertSame('I', $linhas[1][0]);
        $this->assertSame(719, strlen($linhas[1]));
        $this->assertStringContainsString(
            'cigan-transferencia-lote-'.$lote->id.'.txt',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_definir_hub_origem_cigan_na_matriz(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $lote = $this->criarLoteCaptacao($c);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $this->actingAs($user)
            ->put(route('admin.captacao.lotes.hub-origem-cigan.update', $lote), [
                'id_unidade_negocio_hub_origem' => $hub->id,
            ])
            ->assertRedirect(route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']))
            ->assertSessionHas('success');

        $this->assertSame($hub->id, $lote->fresh()->id_unidade_negocio_hub_origem);
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
