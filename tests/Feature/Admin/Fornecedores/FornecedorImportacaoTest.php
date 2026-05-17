<?php

namespace Tests\Feature\Admin\Fornecedores;

use App\Enums\Permissions;
use App\Jobs\Fornecedores\ProcessarPreviewImportacaoFornecedoresJob;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\FornecedorHistorico;
use App\Models\FornecedorImportacao;
use App\Services\Fornecedores\FornecedorImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class FornecedorImportacaoTest extends FornecedorTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'fornecedores.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.fornecedores.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', FornecedorImportacao::STATUS_AGUARDANDO);

        $importacao = FornecedorImportacao::query()->firstOrFail();

        $this->assertSame($user->id, $importacao->user_id);
        $this->assertTrue(Storage::disk('local')->exists($importacao->arquivo_path));
        Queue::assertPushed(
            ProcessarPreviewImportacaoFornecedoresJob::class,
            fn (ProcessarPreviewImportacaoFornecedoresJob $job): bool => $job->queue === 'imports',
        );
    }

    public function test_upload_retorna_rapido_sem_processar_planilha_na_request(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'fornecedores.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.fornecedores.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', FornecedorImportacao::STATUS_AGUARDANDO)
            ->assertJsonStructure(['urls' => ['status', 'resultado', 'confirmar']]);

        $this->assertSame(1, FornecedorImportacao::query()->count());
        $this->assertSame(0, Fornecedor::query()->count());
    }

    public function test_job_processa_nova_existente_sem_alteracao_e_com_alteracao(): void
    {
        Storage::fake('local');
        $existenteIgual = Fornecedor::factory()->create([
            'id_cigam' => '8001',
            'razao_social' => 'Fornecedor Igual',
            'fantasia' => 'Igual Fantasia',
            'cnpj_cpf' => '11111111000111',
        ]);
        $existenteAlterado = Fornecedor::factory()->create([
            'id_cigam' => '8002',
            'razao_social' => 'Fornecedor Antigo',
            'cnpj_cpf' => '22222222000122',
        ]);

        $path = $this->storeSpreadsheet([
            ['8003', 'Fornecedor Novo', 'Nova Fantasia', '33333333000133', 'CEARA'],
            [$existenteIgual->id_cigam, $existenteIgual->razao_social, $existenteIgual->fantasia, $existenteIgual->cnpj_cpf, 'CEARA'],
            [$existenteAlterado->id_cigam, 'Fornecedor Alterado', $existenteAlterado->fantasia, $existenteAlterado->cnpj_cpf, 'CEARA'],
        ]);

        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fornecedoresManager()->id,
            'arquivo_original' => 'fornecedores.xlsx',
            'arquivo_path' => $path,
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFornecedoresJob($importacao->id))
            ->handle(app(FornecedorImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(FornecedorImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(1, $importacao->atualizacoes_count);
        $this->assertSame(100, $importacao->percentual);
    }

    public function test_importacao_resolve_estado_por_abreviacao(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['8010', 'Fornecedor CE', null, '33333333000133', ' ce '],
        ]);

        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fornecedoresManager()->id,
            'arquivo_original' => 'fornecedores.xlsx',
            'arquivo_path' => $path,
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFornecedoresJob($importacao->id))
            ->handle(app(FornecedorImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(0, $importacao->erros_count);
        $this->assertSame(Estado::ID_CEARA, $importacao->resultado['novas'][0]['dados']['id_estado']);
    }

    public function test_importacao_resolve_estado_por_nome(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['8011', 'Fornecedor CEARA', null, '33333333000133', ' Ceará '],
        ]);

        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fornecedoresManager()->id,
            'arquivo_original' => 'fornecedores.xlsx',
            'arquivo_path' => $path,
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFornecedoresJob($importacao->id))
            ->handle(app(FornecedorImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(0, $importacao->erros_count);
        $this->assertSame(Estado::ID_CEARA, $importacao->resultado['novas'][0]['dados']['id_estado']);
    }

    public function test_status_endpoint_expoe_progresso_mensagem_e_erros(): void
    {
        $user = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'fornecedores.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/teste.xlsx',
            'status' => FornecedorImportacao::STATUS_PROCESSANDO,
            'total_linhas' => 10,
            'linhas_processadas' => 3,
            'percentual' => 30,
            'erros_count' => 1,
            'resultado' => [
                'erros' => [[
                    'linha' => 2,
                    'id_cigam' => '000001',
                    'erros' => ['Estado inválido.'],
                ]],
            ],
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.fornecedores.importar.status', $importacao))
            ->assertOk()
            ->assertJsonPath('status', FornecedorImportacao::STATUS_PROCESSANDO)
            ->assertJsonPath('progresso', 30)
            ->assertJsonPath('linhas_processadas', 3)
            ->assertJsonPath('total_linhas', 10)
            ->assertJsonPath('erros.0.id_cigam', '000001')
            ->assertJsonPath('mensagem', 'Processados 3 de 10 registros.');
    }

    public function test_status_retorna_posicao_e_arquivos_na_frente_ignorando_finalizadas(): void
    {
        Cache::forget(FornecedorImportacaoProcessor::HEARTBEAT_CACHE_KEY);

        $user = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'concluida.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/concluida.xlsx',
            'status' => FornecedorImportacao::STATUS_CONCLUIDO,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
        FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'falhou.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/falhou.xlsx',
            'status' => FornecedorImportacao::STATUS_FALHOU,
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(4),
        ]);
        FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'processando.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/processando.xlsx',
            'status' => FornecedorImportacao::STATUS_PROCESSANDO,
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);
        FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'aguardando-antes.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/aguardando-antes.xlsx',
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);
        $atual = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'atual.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/atual.xlsx',
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.fornecedores.importar.status', $atual))
            ->assertOk()
            ->assertJsonPath('posicao_fila', 3)
            ->assertJsonPath('arquivos_na_frente', 2)
            ->assertJsonPath('total_aguardando', 2)
            ->assertJsonPath('total_processando', 1)
            ->assertJsonPath('worker_status', 'INATIVO')
            ->assertJsonPath('mensagem', 'Não foi detectado worker ativo para a fila de importações. Verifique o queue:work ou Supervisor.');
    }

    public function test_status_lista_processando_agora_com_usuario_sem_expor_erros_de_terceiros(): void
    {
        $owner = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        $outro = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        $processando = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $outro->id,
            'arquivo_original' => 'outro.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/outro.xlsx',
            'status' => FornecedorImportacao::STATUS_PROCESSANDO,
            'percentual' => 45,
            'linhas_processadas' => 45,
            'total_linhas' => 100,
            'started_at' => now()->subMinute(),
            'resultado' => [
                'erros' => [['erros' => ['Erro privado de outro usuário']]],
            ],
        ]);
        $atual = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'arquivo_original' => 'atual.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/atual.xlsx',
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('admin.fornecedores.importar.status', $atual))
            ->assertOk()
            ->assertJsonPath('processando_agora.0.id', $processando->id)
            ->assertJsonPath('processando_agora.0.arquivo_original', 'outro.xlsx')
            ->assertJsonPath('processando_agora.0.usuario_nome', $outro->name)
            ->assertJsonPath('processando_agora.0.progresso', 45);

        $this->assertArrayNotHasKey('erros', $response->json('processando_agora.0'));
        $this->assertStringNotContainsString('Erro privado', $response->getContent());
    }

    public function test_programador_pode_monitorar_status_de_outra_importacao(): void
    {
        $owner = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        $programador = $this->programadorUser();
        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'arquivo_original' => 'terceiro.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/terceiro.xlsx',
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        $this->actingAs($programador)
            ->getJson(route('admin.fornecedores.importar.status', $importacao))
            ->assertOk()
            ->assertJsonPath('arquivo_original', 'terceiro.xlsx')
            ->assertJsonPath('usuario_nome', $owner->name);
    }

    public function test_worker_status_retorna_ativo_com_heartbeat_recente(): void
    {
        Cache::put(FornecedorImportacaoProcessor::HEARTBEAT_CACHE_KEY, now()->toIso8601String(), 120);

        $user = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]);
        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'atual.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/atual.xlsx',
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.fornecedores.importar.status', $importacao))
            ->assertOk()
            ->assertJsonPath('worker_status', 'ATIVO');
    }

    public function test_erro_de_linha_e_registrado_sem_falhar_importacao(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['8012', 'Fornecedor Invalido', null, '123', 'CE'],
            ['8013', 'Fornecedor Valido', null, '33333333000133', 'CE'],
        ]);

        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fornecedoresManager()->id,
            'arquivo_original' => 'fornecedores.xlsx',
            'arquivo_path' => $path,
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFornecedoresJob($importacao->id))
            ->handle(app(FornecedorImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(FornecedorImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->erros_count);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame('008012', $importacao->resultado['erros'][0]['id_cigam']);
    }

    public function test_arquivo_inexistente_marca_importacao_como_falhou(): void
    {
        Storage::fake('local');

        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fornecedoresManager()->id,
            'arquivo_original' => 'fornecedores.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/nao-existe.xlsx',
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        try {
            (new ProcessarPreviewImportacaoFornecedoresJob($importacao->id))
                ->handle(app(FornecedorImportacaoProcessor::class));
            $this->fail('Era esperado erro estrutural por arquivo inexistente.');
        } catch (RuntimeException) {
        }

        $importacao->refresh();

        $this->assertSame(FornecedorImportacao::STATUS_FALHOU, $importacao->status);
        $this->assertNotNull($importacao->finished_at);
        $this->assertStringContainsString('Arquivo de importação não encontrado', (string) $importacao->erro_mensagem);
    }

    public function test_importacao_nao_aceita_queue_sync_em_producao(): void
    {
        Storage::fake('local');
        Queue::fake();

        config(['queue.default' => 'sync']);
        $this->app->detectEnvironment(fn (): string => 'production');

        $arquivo = UploadedFile::fake()->create(
            'fornecedores.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->withoutMiddleware()
            ->actingAs($this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR]))
            ->postJson(route('admin.fornecedores.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Importações de fornecedores exigem QUEUE_CONNECTION=database ou redis em produção.');

        Queue::assertNothingPushed();
        $this->assertSame(0, FornecedorImportacao::query()->count());
    }

    public function test_confirmacao_cria_atualiza_com_historico(): void
    {
        $user = $this->userWithPermissions([Permissions::FORNECEDORES_IMPORTAR_CONFIRMAR]);
        $fornecedor = Fornecedor::factory()->create([
            'id_cigam' => '8101',
            'razao_social' => 'Antes',
            'cnpj_cpf' => '44444444000144',
        ]);

        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'fornecedores.xlsx',
            'arquivo_path' => 'fornecedores/importacoes/teste.xlsx',
            'status' => FornecedorImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    $this->importRow(1, '8102', 'Criado', '55555555000155', Estado::ID_CEARA),
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'fornecedor_id' => $fornecedor->id,
                    'id_cigam' => '8101',
                    'linha' => 3,
                    'dados_atuais' => [
                        'id_cigam' => '8101',
                        'id_estado' => (int) $fornecedor->id_estado,
                        'razao_social' => 'Antes',
                        'fantasia' => $fornecedor->fantasia,
                        'cnpj_cpf' => '44444444000144',
                    ],
                    'dados_novos' => [
                        'id_cigam' => '8101',
                        'id_estado' => (int) $fornecedor->id_estado,
                        'razao_social' => 'Depois',
                        'fantasia' => $fornecedor->fantasia,
                        'cnpj_cpf' => '44444444000144',
                    ],
                    'campos_alterados' => [['campo' => 'razao_social', 'atual' => 'Antes', 'novo' => 'Depois']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.fornecedores.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('fornecedores', ['id_cigam' => '008102', 'razao_social' => 'CRIADO']);
        $this->assertSame('DEPOIS', $fornecedor->fresh()->razao_social);
        $this->assertDatabaseHas('fornecedor_historicos', [
            'acao' => FornecedorHistorico::ACAO_IMPORTACAO_CRIACAO,
        ]);
    }

    /**
     * @param  list<array{0:mixed,1:mixed,2:mixed,3:mixed,4:mixed}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID CIGAM', 'Razão social', 'Fantasia', 'CPF/CNPJ', 'Estado'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'fornecedores-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'fornecedores/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function importRow(int $rowId, string $idCigam, string $razaoSocial, string $cnpjCpf, int $idEstado): array
    {
        return [
            'row_id' => $rowId,
            'linha' => $rowId + 1,
            'dados' => [
                'id_cigam' => $idCigam,
                'id_estado' => $idEstado,
                'razao_social' => mb_strtoupper($razaoSocial),
                'fantasia' => null,
                'cnpj_cpf' => $cnpjCpf,
            ],
        ];
    }
}
