<?php

namespace Tests\Feature\Admin\Fornecedores;

use App\Enums\Permissions;
use App\Jobs\Fornecedores\GerarPdfFornecedoresJob;
use App\Models\Fornecedor;
use App\Models\FornecedorExportacao;
use App\Queries\FornecedorQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FornecedorExportacaoTest extends FornecedorTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::FORNECEDORES_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.fornecedores.exportacoes.pdf.iniciar'), [
                'search' => 'Teste',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', FornecedorExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfFornecedoresJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        Fornecedor::factory()->create(['razao_social' => 'Fornecedor PDF']);

        $exportacao = FornecedorExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fornecedoresManager()->id,
            'tipo' => FornecedorExportacao::TIPO_PDF,
            'status' => FornecedorExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'Fornecedor PDF', 'sort' => 'razao_social', 'direction' => 'asc'],
        ]);

        (new GerarPdfFornecedoresJob($exportacao->id))->handle(app(FornecedorQuery::class));

        $exportacao->refresh();

        $this->assertSame(FornecedorExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }
}
