<?php

namespace Tests\Feature\Admin\Grupos;

use App\Enums\Permissions;
use App\Jobs\Grupos\GerarPdfGruposJob;
use App\Models\Grupo;
use App\Models\GrupoExportacao;
use App\Queries\GrupoQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GrupoExportacaoTest extends GrupoTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::GRUPOS_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.grupos.exportacoes.pdf.iniciar'), [
                'search' => 'Teste',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', GrupoExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfGruposJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        Grupo::factory()->create(['nome' => 'GRUPO PDF']);

        $exportacao = GrupoExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->gruposManager()->id,
            'tipo' => GrupoExportacao::TIPO_PDF,
            'status' => GrupoExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'GRUPO PDF', 'sort' => 'nome', 'direction' => 'asc'],
        ]);

        (new GerarPdfGruposJob($exportacao->id))->handle(app(GrupoQuery::class));

        $exportacao->refresh();

        $this->assertSame(GrupoExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }
}
