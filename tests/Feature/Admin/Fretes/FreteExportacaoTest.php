<?php

namespace Tests\Feature\Admin\Fretes;

use App\Enums\Permissions;
use App\Jobs\Fretes\GerarPdfFretesJob;
use App\Models\Frete;
use App\Models\FreteExportacao;
use App\Queries\FreteQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FreteExportacaoTest extends FreteTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::FRETES_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.fretes.exportacoes.pdf.iniciar'), [
                'search' => 'Teste',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', FreteExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfFretesJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        Frete::factory()->create(['nome' => 'FRETE PDF']);

        $exportacao = FreteExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fretesManager()->id,
            'tipo' => FreteExportacao::TIPO_PDF,
            'status' => FreteExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'FRETE PDF', 'sort' => 'nome', 'direction' => 'asc'],
        ]);

        (new GerarPdfFretesJob($exportacao->id))->handle(app(FreteQuery::class));

        $exportacao->refresh();

        $this->assertSame(FreteExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }
}
