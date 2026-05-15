<?php

namespace Tests\Feature\Admin\Empresas;

use App\Enums\Permissions;
use App\Jobs\Empresas\GerarPdfEmpresasJob;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaExportacao;
use App\Queries\EmpresaQuery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmpresaExportacaoTest extends EmpresasTestCase
{
    public function test_usuario_com_permissao_solicita_pdf_e_cria_registro(): void
    {
        Queue::fake();
        $user = $this->userWithPermissions([Permissions::EMPRESAS_EXPORTAR_PDF]);

        $response = $this->actingAs($user)
            ->postJson(route('admin.empresas.exportacoes.pdf.iniciar'), [
                'search' => 'Cliente',
                'sort' => 'id_cigam',
                'direction' => 'asc',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', EmpresaExportacao::STATUS_AGUARDANDO)
            ->assertJsonStructure([
                'uuid',
                'status',
                'mensagem',
                'created_at',
                'urls' => ['status', 'download'],
            ]);

        $this->assertNotNull($response->json('created_at'));

        $exportacao = EmpresaExportacao::query()->firstOrFail();

        $this->assertSame($user->id, $exportacao->user_id);
        $this->assertSame('Cliente', $exportacao->filtros['search']);
        $this->assertSame('id_cigam', $exportacao->filtros['sort']);
        $this->assertSame('asc', $exportacao->filtros['direction']);
        $this->assertSame(
            route('admin.empresas.exportacoes.status', $exportacao, false),
            $response->json('urls.status'),
        );
        $this->assertSame(
            route('admin.empresas.exportacoes.download', $exportacao, false),
            $response->json('urls.download'),
        );
        $this->assertStringContainsString($exportacao->uuid, $response->json('urls.download'));
        $this->assertStringNotContainsString('/storage/', $response->json('urls.download'));
        $this->assertNotEmpty($response->json('mensagem'));
        Queue::assertPushed(GerarPdfEmpresasJob::class);
    }

    public function test_endpoint_de_status_retorna_payload_para_ui(): void
    {
        Storage::fake('local');
        $owner = $this->userWithPermissions([Permissions::EMPRESAS_EXPORTAR_PDF]);
        Storage::disk('local')->put('empresas/exportacoes/relatorio.pdf', '%PDF-1.4 ok');

        $aguardando = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_AGUARDANDO,
        ]);

        $this->actingAs($owner)
            ->getJson(route('admin.empresas.exportacoes.status', $aguardando))
            ->assertOk()
            ->assertJsonPath('status', EmpresaExportacao::STATUS_AGUARDANDO)
            ->assertJsonPath('download_url', null)
            ->assertJsonPath('mensagem', 'O PDF foi solicitado e aguarda o worker iniciar o processamento.')
            ->assertJsonStructure(['created_at', 'started_at', 'finished_at']);

        $concluido = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_CONCLUIDO,
            'arquivo_path' => 'empresas/exportacoes/relatorio.pdf',
            'arquivo_nome' => 'relatorio.pdf',
            'total_registros' => 3,
        ]);

        $this->actingAs($owner)
            ->getJson(route('admin.empresas.exportacoes.status', $concluido))
            ->assertOk()
            ->assertJsonPath('status', EmpresaExportacao::STATUS_CONCLUIDO)
            ->assertJsonPath('total_registros', 3)
            ->assertJsonPath('mensagem', 'O relatório foi gerado com sucesso.')
            ->assertJsonPath(
                'download_url',
                route('admin.empresas.exportacoes.download', $concluido, false),
            );
        $this->assertStringContainsString(
            $concluido->uuid,
            route('admin.empresas.exportacoes.download', $concluido, false),
        );

        $falhou = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_FALHOU,
            'erro_mensagem' => 'Falha customizada',
        ]);

        $this->actingAs($owner)
            ->getJson(route('admin.empresas.exportacoes.status', $falhou))
            ->assertOk()
            ->assertJsonPath('status', EmpresaExportacao::STATUS_FALHOU)
            ->assertJsonPath('mensagem', 'Falha customizada')
            ->assertJsonPath('download_url', null);
    }

    public function test_listagem_renderiza_card_de_status_no_topo_da_pagina(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR, Permissions::EMPRESAS_EXPORTAR_PDF]);

        $response = $this->actingAs($user)
            ->get(route('admin.empresas.index'))
            ->assertOk();

        $html = $response->getContent();

        $this->assertNotFalse(strpos($html, 'id="card-exportacao-pdf"'));
        $this->assertNotFalse(strpos($html, 'id="btn-gerar-pdf"'));
        $this->assertNotFalse(strpos($html, 'id="pdf-status-contador"'));
        $this->assertNotFalse(strpos($html, 'id="pdf-aviso-15s"'));
        $this->assertNotFalse(strpos($html, 'id="pdf-aviso-60s"'));
        $this->assertNotFalse(strpos($html, 'queue:work --queue=empresas-exportacao'));

        $posCard = strpos($html, 'id="card-exportacao-pdf"');
        $posTabela = strpos($html, 'id="empresas-table-root"');
        $this->assertNotFalse($posTabela);
        $this->assertLessThan($posTabela, $posCard, 'O card de status deve aparecer acima da tabela.');

        $this->assertFalse(strpos($html, 'Fallback síncrono'));
        $this->assertFalse(strpos($html, 'fallback síncrono temporário'));
    }

    public function test_job_gera_arquivo_pdf_respeitando_filtro_de_pesquisa(): void
    {
        Storage::fake('local');
        $user = $this->empresaManager();
        Cliente::factory()->create(['razao_social' => 'Cliente PDF']);
        Cliente::factory()->create(['razao_social' => 'Outro Registro']);
        $exportacao = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_AGUARDANDO,
            'filtros' => ['search' => 'Cliente PDF', 'sort' => 'nome_exibicao', 'direction' => 'asc'],
        ]);

        (new GerarPdfEmpresasJob($exportacao->id))->handle(app(EmpresaQuery::class));

        $exportacao->refresh();

        $this->assertSame(EmpresaExportacao::STATUS_CONCLUIDO, $exportacao->status);
        $this->assertSame(1, $exportacao->total_registros);
        $this->assertNotNull($exportacao->arquivo_path);
        $this->assertTrue(Storage::disk('local')->exists($exportacao->arquivo_path));
    }

    public function test_job_falha_com_mensagem_amigavel_quando_pdf_excede_limite_de_registros(): void
    {
        Storage::fake('local');
        $user = $this->empresaManager();
        Empresa::factory()->count(GerarPdfEmpresasJob::LIMITE_REGISTROS_PDF + 1)->create();
        $exportacao = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_AGUARDANDO,
            'filtros' => ['sort' => 'nome_exibicao', 'direction' => 'asc'],
        ]);

        try {
            (new GerarPdfEmpresasJob($exportacao->id))->handle(app(EmpresaQuery::class));
            $this->fail('A exportação deveria falhar ao exceder o limite de registros.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('suporta até 1000 registros', $e->getMessage());
        }

        $exportacao->refresh();

        $this->assertSame(EmpresaExportacao::STATUS_FALHOU, $exportacao->status);
        $this->assertSame(GerarPdfEmpresasJob::LIMITE_REGISTROS_PDF + 1, $exportacao->total_registros);
        $this->assertSame(
            'A exportação PDF suporta até 1000 registros por vez. Utilize filtros para reduzir o resultado.',
            $exportacao->erro_mensagem,
        );
        $this->assertNull($exportacao->arquivo_path);
    }

    public function test_download_somente_para_dono_ou_programador(): void
    {
        Storage::fake('local');
        $owner = $this->userWithPermissions([Permissions::EMPRESAS_EXPORTAR_PDF]);
        $other = $this->userWithPermissions([Permissions::EMPRESAS_EXPORTAR_PDF]);
        $programador = $this->programadorUser();
        Storage::disk('local')->put('empresas/exportacoes/teste.pdf', '%PDF-1.4 teste');

        $exportacao = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_CONCLUIDO,
            'arquivo_path' => 'empresas/exportacoes/teste.pdf',
            'arquivo_nome' => 'teste.pdf',
            'total_registros' => 1,
        ]);

        $this->actingAs($other)
            ->get(route('admin.empresas.exportacoes.download', $exportacao))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('admin.empresas.exportacoes.download', $exportacao))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition');

        $this->actingAs($programador)
            ->get(route('admin.empresas.exportacoes.download', $exportacao))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_download_por_uuid_retorna_pdf_sem_usar_caminho_publico(): void
    {
        Storage::fake('local');
        $owner = $this->userWithPermissions([Permissions::EMPRESAS_EXPORTAR_PDF]);
        Storage::disk('local')->put('empresas/exportacoes/uuid-download.pdf', '%PDF-1.4 teste');

        $exportacao = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_CONCLUIDO,
            'arquivo_path' => 'empresas/exportacoes/uuid-download.pdf',
            'arquivo_nome' => 'uuid-download.pdf',
            'total_registros' => 1,
        ]);

        $downloadUrl = route('admin.empresas.exportacoes.download', $exportacao, false);

        $this->assertSame('/admin/empresas/exportacoes/'.$exportacao->uuid.'/download', $downloadUrl);
        $this->assertStringNotContainsString('/storage/', $downloadUrl);
        $this->assertStringNotContainsString('storage/app', $downloadUrl);

        $this->actingAs($owner)
            ->get($downloadUrl)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition');
    }

    public function test_download_de_arquivo_inexistente_retorna_404_controlado(): void
    {
        Storage::fake('local');
        $owner = $this->userWithPermissions([Permissions::EMPRESAS_EXPORTAR_PDF]);

        $exportacao = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_CONCLUIDO,
            'arquivo_path' => 'empresas/exportacoes/nao-existe.pdf',
            'arquivo_nome' => 'nao-existe.pdf',
            'total_registros' => 1,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.empresas.exportacoes.download', $exportacao))
            ->assertNotFound();
    }

    public function test_download_retorna_conflito_quando_pdf_ainda_nao_esta_pronto(): void
    {
        $owner = $this->userWithPermissions([Permissions::EMPRESAS_EXPORTAR_PDF]);

        $exportacao = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_PROCESSANDO,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.empresas.exportacoes.download', $exportacao))
            ->assertStatus(409);
    }

    public function test_pdf_usa_ordenacao_numerica_por_id_cigam(): void
    {
        $clienteIds = [];
        foreach (['1000', '100', '20', '10', '2', '1'] as $idCigam) {
            $cliente = Cliente::factory()->create([
                'id_cigam' => $idCigam,
                'razao_social' => 'ORDEM '.$idCigam,
            ]);
            $clienteIds[] = $cliente->id;
        }

        $query = app(EmpresaQuery::class)->aplicarFiltros(
            Empresa::query()
                ->withEntidadeParaListagem()
                ->where('entidade_type', Cliente::class)
                ->whereIn('entidade_id', $clienteIds),
            app(EmpresaQuery::class)->normalizarFiltros([
                'sort' => 'id_cigam',
                'direction' => 'asc',
            ]),
        );

        $ids = $query->get()->map(fn (Empresa $e) => $e->idCigamExibicao())->all();

        $this->assertSame(['000001', '000002', '000010', '000020', '000100', '001000'], $ids);
    }
}
