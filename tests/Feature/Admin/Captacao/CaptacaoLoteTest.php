<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Movimentacao;

class CaptacaoLoteTest extends CaptacaoTestCase
{
    public function test_abre_lote_do_dia_por_galpao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $response = $this->actingAs($user)->post(route('admin.captacao.lotes.store'), [
            'data_referencia' => '2026-05-29',
            'id_captacao_carteira' => $c['carteira']->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('captacao_lotes', [
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento->value,
        ]);
    }

    public function test_listagem_exibe_botoes_ver_e_editar(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.index'))
            ->assertOk()
            ->assertSee(route('admin.captacao.lotes.show', $lote), false)
            ->assertSee(route('admin.captacao.matriz.index', ['lote' => $lote->id]), false)
            ->assertSee('Matriz', false)
            ->assertSee('captacao-lote-row--captacao', false)
            ->assertSee('Captação em andamento', false);
    }

    public function test_lote_show_exibe_apenas_proxima_acao_pipeline(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $html = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.show', $lote))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Iniciar transferência'));
        $this->assertStringNotContainsString('Validar transferências', $html);
        $this->assertStringNotContainsString('Finalizar vendas SB', $html);
    }

    public function test_lote_show_exibe_linha_do_tempo_de_status(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['status' => CaptacaoLoteStatus::TransferenciaCiganIniciada]);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.show', $lote))
            ->assertOk()
            ->assertSee('Linha do tempo do lote', false)
            ->assertSee('Neste momento:', false)
            ->assertSee('Cigan', false)
            ->assertSee('Concluído', false)
            ->assertSee('Próximo', false);
    }

    public function test_matriz_exibe_proxima_acao_pipeline(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $html = $this->actingAs($user)
            ->get(route('admin.captacao.matriz.index', ['lote' => $lote->id]))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Finalizar captação (faturamento)'));
        $this->assertStringNotContainsString('Iniciar transferência', $html);
    }

    public function test_criar_pedido_nao_gera_movimentacao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $antes = Movimentacao::query()->count();

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'quantidade' => 10],
            ],
        ])->assertRedirect();

        $this->assertSame($antes, Movimentacao::query()->count());
        $this->assertDatabaseHas('pedido_itens', [
            'id_fruta' => $c['fruta']->id,
            'quantidade' => '10.000',
        ]);
    }
}
