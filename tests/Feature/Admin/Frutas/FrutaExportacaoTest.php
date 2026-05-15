<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\Permissions;
use App\Jobs\Frutas\GerarPdfFrutasJob;
use App\Models\Fruta;
use App\Models\FrutaExportacao;
use App\Queries\FrutaQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrutaExportacaoTest extends FrutaTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::FRUTAS_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.frutas.exportacoes.pdf.iniciar'), [
                'search' => 'Teste',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', FrutaExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfFrutasJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        Fruta::factory()->create(['nome' => 'FRUTA PDF']);

        $exportacao = FrutaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->frutasManager()->id,
            'tipo' => FrutaExportacao::TIPO_PDF,
            'status' => FrutaExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'FRUTA PDF', 'sort' => 'nome', 'direction' => 'asc'],
        ]);

        (new GerarPdfFrutasJob($exportacao->id))->handle(app(FrutaQuery::class));

        $exportacao->refresh();

        $this->assertSame(FrutaExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }
}
