<?php

namespace Tests\Feature\Admin\Veiculos;

use App\Enums\Permissions;
use App\Jobs\Veiculos\GerarPdfVeiculosJob;
use App\Models\UnidadeNegocio;
use App\Models\Veiculo;
use App\Models\VeiculoExportacao;
use App\Queries\VeiculoQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VeiculoExportacaoTest extends VeiculoTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::VEICULOS_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.veiculos.exportacoes.pdf.iniciar'), [
                'search' => 'Teste',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', VeiculoExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfVeiculosJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        $unidade = UnidadeNegocio::factory()->create();
        Veiculo::factory()->create([
            'nome' => 'Veículo PDF',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $exportacao = VeiculoExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->veiculosManager()->id,
            'tipo' => VeiculoExportacao::TIPO_PDF,
            'status' => VeiculoExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'Veículo PDF', 'sort' => 'nome', 'direction' => 'asc'],
        ]);

        (new GerarPdfVeiculosJob($exportacao->id))->handle(app(VeiculoQuery::class));

        $exportacao->refresh();

        $this->assertSame(VeiculoExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }
}
