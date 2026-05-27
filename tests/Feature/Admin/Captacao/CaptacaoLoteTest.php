<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\Permissions;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
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
            ->assertSee('data-search-select', false)
            ->assertSee('admin-search-select.js', false)
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

        $html = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.show', $lote))
            ->assertOk()
            ->assertDontSee('Linha do tempo do lote', false)
            ->assertSee('Neste momento:', false)
            ->assertSee('Cigam', false)
            ->assertSee('Concluído', false)
            ->assertSee('Próximo', false)
            ->getContent();

        $this->assertStringContainsString('captacao-lote-timeline', $html);
        $this->assertStringContainsString('Status atual:', $html);
        $this->assertStringContainsString($lote->unidadeFaturamento->nome, $html);
        $this->assertStringContainsString($lote->unidadeGalpao->nome, $html);
        $timelinePos = strpos($html, 'captacao-lote-timeline');
        $statusPos = strpos($html, 'Status atual:');
        $this->assertNotFalse($timelinePos);
        $this->assertNotFalse($statusPos);
        $this->assertGreaterThan($timelinePos, $statusPos);
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

    public function test_recupera_lote_em_andamento_mesmo_dia_carteira(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.store'), [
                'data_referencia' => $lote->data_referencia->toDateString(),
                'id_captacao_carteira' => $c['carteira']->id,
            ])
            ->assertRedirect(route('admin.captacao.lotes.show', $lote))
            ->assertSessionHas('success');

        $this->assertSame(1, CaptacaoLote::query()
            ->whereDate('data_referencia', $lote->data_referencia)
            ->where('id_captacao_carteira', $c['carteira']->id)
            ->count());
    }

    public function test_abre_novo_lote_mesmo_dia_carteira_apos_sair_de_em_andamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $loteAnterior = $this->criarLoteCaptacao($c);
        $loteAnterior->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.store'), [
                'data_referencia' => $loteAnterior->data_referencia->toDateString(),
                'id_captacao_carteira' => $c['carteira']->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $lotes = CaptacaoLote::query()
            ->whereDate('data_referencia', $loteAnterior->data_referencia)
            ->where('id_captacao_carteira', $c['carteira']->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $lotes);
        $this->assertSame(CaptacaoLoteStatus::AguardandoTransferenciaCigan, $lotes[0]->status);
        $this->assertSame(CaptacaoLoteStatus::CaptacaoEmAndamento, $lotes[1]->status);
    }

    public function test_abre_captacao_complementar_com_dia_faturamento_finalizado(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lotePrincipal = $this->criarLoteCaptacao($c, '2026-05-27');
        $lotePrincipal->update(['status' => CaptacaoLoteStatus::TransferenciaFinalizada]);

        \App\Models\Captacao\CaptacaoFaturamentoDia::query()->updateOrCreate(
            [
                'data_referencia' => '2026-05-27',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            ],
            [
                'status' => \App\Enums\CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada,
                'finalizado_em' => now(),
            ],
        );

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.store'), [
                'data_referencia' => '2026-05-27',
                'id_captacao_carteira' => $c['carteira']->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $lotes = CaptacaoLote::query()
            ->whereDate('data_referencia', '2026-05-27')
            ->where('id_captacao_carteira', $c['carteira']->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $lotes);
        $this->assertSame(CaptacaoLoteStatus::TransferenciaFinalizada, $lotes[0]->status);
        $this->assertSame(CaptacaoLoteStatus::CaptacaoEmAndamento, $lotes[1]->status);
    }

    public function test_abre_novo_lote_com_dia_finalizado_em_qualquer_status_exceto_em_andamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c, '2026-05-27');
        $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        \App\Models\Captacao\CaptacaoFaturamentoDia::query()->updateOrCreate(
            [
                'data_referencia' => '2026-05-27',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            ],
            ['status' => \App\Enums\CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada],
        );

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.store'), [
                'data_referencia' => '2026-05-27',
                'id_captacao_carteira' => $c['carteira']->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertCount(2, CaptacaoLote::query()
            ->whereDate('data_referencia', '2026-05-27')
            ->where('id_captacao_carteira', $c['carteira']->id)
            ->get());
    }

    public function test_captacao_complementar_permanece_em_andamento_ao_abrir_com_dia_finalizado(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $loteAnterior = $this->criarLoteCaptacao($c, '2026-05-27');
        $loteAnterior->update(['status' => CaptacaoLoteStatus::TransferenciaFinalizada]);

        \App\Models\Captacao\CaptacaoFaturamentoDia::query()->updateOrCreate(
            [
                'data_referencia' => '2026-05-27',
                'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            ],
            ['status' => \App\Enums\CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada],
        );

        $this->actingAs($user)
            ->post(route('admin.captacao.lotes.store'), [
                'data_referencia' => '2026-05-27',
                'id_captacao_carteira' => $c['carteira']->id,
            ])
            ->assertRedirect();

        $loteNovo = CaptacaoLote::query()
            ->whereDate('data_referencia', '2026-05-27')
            ->where('id_captacao_carteira', $c['carteira']->id)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($loteNovo);

        $this->actingAs($user)
            ->get(route('admin.captacao.lotes.show', $loteNovo))
            ->assertOk();

        $this->assertSame(
            CaptacaoLoteStatus::CaptacaoEmAndamento,
            $loteNovo->fresh()->status,
        );
    }

    public function test_listagem_exibe_botao_excluir_apenas_em_andamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $loteEmAndamento = $this->criarLoteCaptacao($c);
        $loteAvancado = $this->criarLoteCaptacao($c, '2026-05-28');
        $loteAvancado->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $html = $this->actingAs($user)
            ->get(route('admin.captacao.lotes.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-confirm-title="Excluir captação"'));
        $this->assertStringContainsString(
            'Excluir a captação de '.$loteEmAndamento->data_referencia->format('d/m/Y'),
            $html,
        );
        $this->assertStringNotContainsString(
            'Excluir a captação de '.$loteAvancado->data_referencia->format('d/m/Y'),
            $html,
        );
    }

    public function test_exclui_captacao_em_andamento_com_pedidos(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)->post(route('admin.captacao.lotes.pedidos.store', $lote), [
            'id_cliente' => $c['cliente']->id,
            'id_captacao_rota' => $c['rota']->id,
            'itens' => [
                ['id_fruta' => $c['fruta']->id, 'quantidade' => 5],
            ],
        ])->assertRedirect();

        $pedidoId = Pedido::query()->where('id_captacao_lote', $lote->id)->value('id');
        $this->assertNotNull($pedidoId);

        $this->actingAs($user)
            ->delete(route('admin.captacao.lotes.destroy', $lote))
            ->assertRedirect(route('admin.captacao.lotes.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('captacao_lotes', ['id' => $lote->id]);
        $this->assertDatabaseMissing('pedidos', ['id' => $pedidoId]);
        $this->assertDatabaseMissing('pedido_itens', ['id_pedido' => $pedidoId]);
    }

    public function test_nao_exclui_captacao_fora_de_andamento(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);
        $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);

        $this->actingAs($user)
            ->delete(route('admin.captacao.lotes.destroy', $lote))
            ->assertRedirect(route('admin.captacao.lotes.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('captacao_lotes', [
            'id' => $lote->id,
            'deleted_at' => null,
        ]);
    }

    public function test_excluir_captacao_exige_permissao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]);
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = $this->criarLoteCaptacao($c);

        $this->actingAs($user)
            ->delete(route('admin.captacao.lotes.destroy', $lote))
            ->assertForbidden();
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
