<?php

namespace Tests\Feature\Admin\UnidadesNegocio;

use App\Enums\Permissions;
use App\Jobs\UnidadesNegocio\GerarPdfUnidadesNegocioJob;
use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioExportacao;
use App\Queries\UnidadeNegocioQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnidadeNegocioExportacaoTest extends UnidadeNegocioTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.unidades-negocio.exportacoes.pdf.iniciar'), [
                'search' => 'Teste',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', UnidadeNegocioExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfUnidadesNegocioJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        UnidadeNegocio::factory()->create(['nome' => 'Unidade PDF']);

        $exportacao = UnidadeNegocioExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->unidadesNegocioManager()->id,
            'tipo' => UnidadeNegocioExportacao::TIPO_PDF,
            'status' => UnidadeNegocioExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'Unidade PDF', 'sort' => 'nome', 'direction' => 'asc'],
        ]);

        (new GerarPdfUnidadesNegocioJob($exportacao->id))->handle(app(UnidadeNegocioQuery::class));

        $exportacao->refresh();

        $this->assertSame(UnidadeNegocioExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }
}
