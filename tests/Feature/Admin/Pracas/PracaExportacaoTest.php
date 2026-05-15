<?php

namespace Tests\Feature\Admin\Pracas;

use App\Enums\Permissions;
use App\Jobs\Pracas\GerarPdfPracasJob;
use App\Models\Praca;
use App\Models\PracaExportacao;
use App\Models\UnidadeNegocio;
use App\Queries\PracaQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PracaExportacaoTest extends PracaTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::PRACAS_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.pracas.exportacoes.pdf.iniciar'), [
                'search' => 'Teste',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', PracaExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfPracasJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        $unidade = UnidadeNegocio::factory()->create();
        Praca::factory()->create([
            'nome' => 'PRACA PDF',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $exportacao = PracaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->pracasManager()->id,
            'tipo' => PracaExportacao::TIPO_PDF,
            'status' => PracaExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'PRACA PDF', 'sort' => 'nome', 'direction' => 'asc'],
        ]);

        (new GerarPdfPracasJob($exportacao->id))->handle(app(PracaQuery::class));

        $exportacao->refresh();

        $this->assertSame(PracaExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }
}
