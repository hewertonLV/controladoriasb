<?php

namespace Tests\Feature\Admin\Clientes;

use App\Enums\Permissions;
use App\Jobs\Clientes\GerarPdfClientesJob;
use App\Models\Cliente;
use App\Models\ClienteExportacao;
use App\Queries\ClienteQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClienteExportacaoTest extends ClienteTestCase
{
    public function test_usuario_com_permissao_solicita_pdf(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::CLIENTES_EXPORTAR_PDF]);

        $this->actingAs($user)
            ->postJson(route('admin.clientes.exportacoes.pdf.iniciar'))
            ->assertAccepted()
            ->assertJsonPath('status', ClienteExportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(GerarPdfClientesJob::class);
    }

    public function test_job_gera_arquivo_pdf(): void
    {
        Storage::fake('local');
        Cliente::factory()->create(['razao_social' => 'Cliente PDF', 'fantasia' => 'Fantasia PDF']);

        $exportacao = ClienteExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->clientesManager()->id,
            'tipo' => ClienteExportacao::TIPO_PDF,
            'status' => ClienteExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'Cliente PDF', 'sort' => 'razao_social', 'direction' => 'asc'],
        ]);

        (new GerarPdfClientesJob($exportacao->id))->handle(app(ClienteQuery::class));

        $exportacao->refresh();

        $this->assertSame(ClienteExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }

    public function test_pdf_exibe_fantasia(): void
    {
        $cliente = Cliente::factory()->create([
            'razao_social' => 'Cliente PDF Fantasia',
            'fantasia' => 'Fantasia Visivel',
        ]);

        $html = view('admin.clientes.pdf', [
            'clientes' => collect([$cliente->load(['praca', 'grupo'])]),
            'filtros' => ['search' => ''],
            'geradoEm' => now(),
            'geradoPor' => 'Teste',
            'limiteRegistros' => 1000,
        ])->render();

        $this->assertStringContainsString('FANTASIA VISIVEL', $html);
    }
}
