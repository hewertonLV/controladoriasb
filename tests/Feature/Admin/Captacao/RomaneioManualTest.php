<?php

namespace Tests\Feature\Admin\Captacao;

use App\Actions\Captacao\ConfirmarRomaneioManualAction;
use App\Actions\Captacao\ConcluirTransferenciaRomaneioManualAction;
use App\Actions\Captacao\IniciarTransferenciaCiganAction;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\UnidadeNegocio;
use App\Support\Captacao\CaptacaoLotePipelineUi;
use Illuminate\Support\Facades\Storage;

class RomaneioManualTest extends CaptacaoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCaptacaoMovimentacao();
    }

    public function test_abre_romaneio_e_redireciona_para_edicao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $response = $this->actingAs($user)->post(route('admin.captacao.romaneio-manual.store'), [
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
        ]);

        $lote = CaptacaoLote::query()->latest('id')->first();
        $response->assertRedirect(route('admin.captacao.romaneio-manual.edit', $lote));
        $this->assertSame(CaptacaoLoteTipo::RomaneioManual, $lote->tipo);
        $this->assertSame(CaptacaoLoteStatus::CaptacaoEmAndamento, $lote->status);
    }

    public function test_adiciona_fruta_e_incrementa_caixas_com_autosave(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);
        $hub = UnidadeNegocio::factory()->create(['is_hub' => true, 'possui_estoque' => true]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => CaptacaoLoteTipo::RomaneioManual,
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.romaneio-manual.frutas.store', $lote), [
                'id_fruta' => $c['fruta']->id,
                'id_unidade_origem_fisica' => $hub->id,
                'motivo' => 'Reposição',
            ])
            ->assertOk()
            ->assertJsonPath('linha.id_fruta', $c['fruta']->id);

        $linha = $lote->manualLinhas()->firstOrFail();

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.romaneio-manual.linhas.update', [$lote, $linha]), [
                'incremento' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('linha.quantidade', 2);

        $this->actingAs($user)
            ->patchJson(route('admin.captacao.romaneio-manual.linhas.update', [$lote, $linha]), [
                'incremento' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('linha.quantidade', 5);
    }

    public function test_pipeline_romaneio_manual_sequencial(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $hub = $this->criarHubComEstoque($c['fruta']);
        $this->criarCoGalpao($c['galpao']);
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id, $hub->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => CaptacaoLoteTipo::RomaneioManual,
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $this->assertSame(
            \App\Enums\CaptacaoLoteAcaoPipeline::ConfirmarRomaneioManual,
            CaptacaoLotePipelineUi::proximaAcao($lote),
        );

        $lote->manualLinhas()->create([
            'id_fruta' => $c['fruta']->id,
            'quantidade' => 5,
            'id_unidade_origem_fisica' => $hub->id,
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.romaneio-manual.confirmar', $lote))
            ->assertRedirect(route('admin.captacao.romaneio-manual.show', $lote));

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::AguardandoTransferenciaCigan, $lote->status);
        $this->assertSame(
            \App\Enums\CaptacaoLoteAcaoPipeline::IniciarTransferencia,
            CaptacaoLotePipelineUi::proximaAcao($lote),
        );

        Storage::fake('local');
        $this->actingAs($user)
            ->post(route('admin.captacao.romaneio-manual.iniciar-transferencia', $lote))
            ->assertRedirect();

        $lote->refresh();
        $this->assertSame(CaptacaoLoteStatus::TransferenciaCiganIniciada, $lote->status);

        $this->actingAs($user)
            ->post(route('admin.captacao.romaneio-manual.concluir-transferencia', $lote))
            ->assertRedirect();

        $this->assertSame(CaptacaoLoteStatus::TransferenciaFinalizada, $lote->fresh()->status);
        $this->assertNull(CaptacaoLotePipelineUi::proximaAcao($lote->fresh()));
    }

    public function test_nao_permite_pular_etapa_iniciar_transferencia(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => CaptacaoLoteTipo::RomaneioManual,
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $this->actingAs($user)
            ->post(route('admin.captacao.romaneio-manual.iniciar-transferencia', $lote))
            ->assertSessionHasErrors('status');
    }

    public function test_exportacao_cigan_romaneio_manual_inclui_unidade_faturamento(): void
    {
        Storage::fake('local');

        $c = $this->cenarioCaptacaoBasico();
        $c['faturamento']->update(['nome' => 'CD ALAGOAS FAT']);
        $c['galpao']->update(['nome' => 'GALPAO CE']);

        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $hub = UnidadeNegocio::factory()->create(['is_hub' => true, 'possui_estoque' => true]);

        $this->actingAs($user)->post(route('admin.captacao.romaneio-manual.store'), [
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
        ]);

        $lote = CaptacaoLote::query()->where('tipo', CaptacaoLoteTipo::RomaneioManual)->firstOrFail();

        $this->actingAs($user)->postJson(route('admin.captacao.romaneio-manual.frutas.store', $lote), [
            'id_fruta' => $c['fruta']->id,
            'id_unidade_origem_fisica' => $hub->id,
        ]);

        app(ConfirmarRomaneioManualAction::class)->executar($lote->fresh());
        app(IniciarTransferenciaCiganAction::class)->executar($lote->fresh(), $user);

        $export = $lote->fresh()->ciganExports()->firstOrFail();
        $conteudo = Storage::disk('local')->get($export->caminho_arquivo);

        $this->assertStringContainsString('unidade_faturamento', (string) $conteudo);
        $this->assertStringContainsString('CD ALAGOAS FAT', (string) $conteudo);
        $this->assertStringContainsString('GALPAO CE', (string) $conteudo);
        $this->assertStringContainsString('TRANSFERENCIA_MANUAL', (string) $conteudo);
    }

    public function test_tela_edicao_exibe_proxima_acao_fechar_romaneio(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();
        $user->unidadesNegocio()->sync([$c['faturamento']->id, $c['galpao']->id]);

        $lote = CaptacaoLote::query()->create([
            'data_referencia' => '2026-05-29',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'tipo' => CaptacaoLoteTipo::RomaneioManual,
            'status' => CaptacaoLoteStatus::CaptacaoEmAndamento,
        ]);

        $this->actingAs($user)
            ->get(route('admin.captacao.romaneio-manual.edit', $lote))
            ->assertOk()
            ->assertSee('Confirmar solicitação', false)
            ->assertDontSee('Iniciar transferência', false);
    }
}
