<?php

namespace Tests\Feature\Admin\UnidadesNegocio;

use App\Enums\Permissions;
use App\Jobs\UnidadesNegocio\ProcessarPreviewImportacaoUnidadesNegocioJob;
use App\Models\Estado;
use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioImportacao;
use App\Services\UnidadesNegocio\UnidadeNegocioImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UnidadeNegocioImportacaoTest extends UnidadeNegocioTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'unidades.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.unidades-negocio.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', UnidadeNegocioImportacao::STATUS_AGUARDANDO);

        $importacao = UnidadeNegocioImportacao::query()->firstOrFail();

        $this->assertSame($user->id, $importacao->user_id);
        $this->assertSame('unidades.xlsx', $importacao->arquivo_original);
        Storage::disk('local')->assertExists($importacao->arquivo_path);
        Queue::assertPushed(ProcessarPreviewImportacaoUnidadesNegocioJob::class);
    }

    public function test_job_processa_nova_sem_alteracao_e_atualizacao(): void
    {
        Storage::fake('local');
        $existenteIgual = UnidadeNegocio::factory()->create([
            'id_cigam' => '000101',
            'nome' => 'UNIDADE IGUAL',
            'razao_social' => 'UNIDADE IGUAL',
        ]);
        $existenteAlterada = UnidadeNegocio::factory()->create([
            'id_cigam' => '000102',
            'nome' => 'NOME ANTIGO',
            'razao_social' => 'NOME ANTIGO',
        ]);

        $path = $this->storeSpreadsheet([
            ['000103', 'UNIDADE NOVA', 'UNIDADE NOVA', '33333333000133', '100.00', 'NÃO', 'CEARA'],
            [
                $existenteIgual->id_cigam,
                $existenteIgual->razao_social,
                $existenteIgual->nome,
                $existenteIgual->cpf_cnpj,
                number_format((float) $existenteIgual->custo_operacional, 2, '.', ''),
                $existenteIgual->possui_estoque ? 'SIM' : 'NÃO',
                'CEARA',
            ],
            [$existenteAlterada->id_cigam, 'RAZAO NOVA', 'NOME NOVO', $existenteAlterada->cpf_cnpj, '50.00', 'NÃO', 'CEARA'],
        ]);

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->unidadesNegocioManager()->id,
            'arquivo_original' => 'unidades.xlsx',
            'arquivo_path' => $path,
            'status' => UnidadeNegocioImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoUnidadesNegocioJob($importacao->id))
            ->handle(app(UnidadeNegocioImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(UnidadeNegocioImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(1, $importacao->atualizacoes_count);
        $this->assertSame('000103', $importacao->resultado['novas'][0]['dados']['id_cigam']);
        $this->assertSame('000101', $importacao->resultado['sem_alteracoes'][0]['id_cigam']);
        $this->assertSame('000102', $importacao->resultado['atualizacoes'][0]['id_cigam']);
    }

    public function test_importacao_resolve_estado_por_nome(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['000104', 'UNIDADE CEARA', 'UNIDADE CEARA', '33333333000133', '100.00', 'NÃO', ' Ceará '],
        ]);

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->unidadesNegocioManager()->id,
            'arquivo_original' => 'unidades.xlsx',
            'arquivo_path' => $path,
            'status' => UnidadeNegocioImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoUnidadesNegocioJob($importacao->id))
            ->handle(app(UnidadeNegocioImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(0, $importacao->erros_count);
        $this->assertSame(Estado::ID_CEARA, $importacao->resultado['novas'][0]['dados']['id_estado']);
    }

    public function test_importacao_aceita_cpf_cnpj_repetido_vazio_e_sanitiza_documento(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['000105', 'UNIDADE DOC 1', 'UNIDADE DOC 1', '11.222.333/0001-81', '100.00', 'NÃO', 'CEARA'],
            ['000106', 'UNIDADE DOC 2', 'UNIDADE DOC 2', '11222333000181', '100.00', 'NÃO', 'CEARA'],
            ['000107', 'UNIDADE SEM DOC', 'UNIDADE SEM DOC', '', '100.00', 'NÃO', 'CEARA'],
        ]);

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->unidadesNegocioManager()->id,
            'arquivo_original' => 'unidades.xlsx',
            'arquivo_path' => $path,
            'status' => UnidadeNegocioImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoUnidadesNegocioJob($importacao->id))
            ->handle(app(UnidadeNegocioImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(0, $importacao->erros_count);
        $this->assertSame(3, $importacao->novas_count);
        $this->assertSame('11222333000181', $importacao->resultado['novas'][0]['dados']['cpf_cnpj']);
        $this->assertSame('11222333000181', $importacao->resultado['novas'][1]['dados']['cpf_cnpj']);
        $this->assertNull($importacao->resultado['novas'][2]['dados']['cpf_cnpj']);
    }

    public function test_confirmacao_cria_e_atualiza(): void
    {
        $user = $this->userWithPermissions([
            Permissions::UNIDADES_NEGOCIO_IMPORTAR_CONFIRMAR,
        ]);
        $existente = UnidadeNegocio::factory()->create([
            'id_cigam' => '000201',
            'nome' => 'ANTES',
            'razao_social' => 'ANTES',
        ]);

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'u.xlsx',
            'arquivo_path' => 'unidades-negocio/importacoes/x.xlsx',
            'status' => UnidadeNegocioImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    [
                        'row_id' => 1,
                        'linha' => 2,
                        'dados' => [
                            'id_cigam' => '000202',
                            'razao_social' => 'CRIADA',
                            'nome' => 'CRIADA',
                            'cpf_cnpj' => '55555555000155',
                            'custo_operacional' => '25.00',
                            'possui_estoque' => false,
                            'id_estado' => Estado::ID_CEARA,
                        ],
                    ],
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'unidade_negocio_id' => $existente->id,
                    'id_cigam' => '000201',
                    'linha' => 3,
                    'dados_atuais' => [
                        'id_cigam' => '000201',
                        'razao_social' => 'ANTES',
                        'nome' => 'ANTES',
                        'cpf_cnpj' => $existente->cpf_cnpj,
                        'custo_operacional' => '0.00',
                        'possui_estoque' => false,
                        'id_estado' => Estado::ID_CEARA,
                    ],
                    'dados_novos' => [
                        'id_cigam' => '000201',
                        'razao_social' => 'DEPOIS',
                        'nome' => 'DEPOIS',
                        'cpf_cnpj' => $existente->cpf_cnpj,
                        'custo_operacional' => '0.00',
                        'possui_estoque' => false,
                        'id_estado' => Estado::ID_CEARA,
                    ],
                    'campos_alterados' => [['campo' => 'nome', 'atual' => 'ANTES', 'novo' => 'DEPOIS']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.unidades-negocio.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('unidades_negocio', ['id_cigam' => '000202', 'nome' => 'CRIADA']);
        $this->assertSame('DEPOIS', $existente->fresh()->nome);
    }

    public function test_confirmacao_permite_cpf_cnpj_repetido_e_vazio(): void
    {
        $user = $this->userWithPermissions([
            Permissions::UNIDADES_NEGOCIO_IMPORTAR_CONFIRMAR,
        ]);
        UnidadeNegocio::factory()->create([
            'id_cigam' => '000301',
            'cpf_cnpj' => '11222333000181',
        ]);

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'u.xlsx',
            'arquivo_path' => 'unidades-negocio/importacoes/x.xlsx',
            'status' => UnidadeNegocioImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    [
                        'row_id' => 1,
                        'linha' => 2,
                        'dados' => [
                            'id_cigam' => '000302',
                            'razao_social' => 'CRIADA DOC REPETIDO',
                            'nome' => 'CRIADA DOC REPETIDO',
                            'cpf_cnpj' => '11222333000181',
                            'custo_operacional' => '25.00',
                            'possui_estoque' => false,
                            'id_estado' => Estado::ID_CEARA,
                        ],
                    ],
                    [
                        'row_id' => 2,
                        'linha' => 3,
                        'dados' => [
                            'id_cigam' => '000303',
                            'razao_social' => 'CRIADA SEM DOC',
                            'nome' => 'CRIADA SEM DOC',
                            'cpf_cnpj' => null,
                            'custo_operacional' => '25.00',
                            'possui_estoque' => false,
                            'id_estado' => Estado::ID_CEARA,
                        ],
                    ],
                ],
                'atualizacoes' => [],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.unidades-negocio.importar.confirmar', $importacao), [
                'row_ids_novas' => [1, 2],
                'row_ids_atualizacoes' => [],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 2)
            ->assertJsonPath('resumo.erros', []);

        $this->assertSame(2, UnidadeNegocio::query()
            ->where('cpf_cnpj', '11222333000181')
            ->count());
        $this->assertDatabaseHas('unidades_negocio', [
            'id_cigam' => '000303',
            'cpf_cnpj' => null,
        ]);
    }

    public function test_confirmacao_atualiza_nome_sem_reativar_unidade_inativa(): void
    {
        $user = $this->userWithPermissions([
            Permissions::UNIDADES_NEGOCIO_IMPORTAR_CONFIRMAR,
        ]);
        $inativa = UnidadeNegocio::factory()->inativa()->create([
            'id_cigam' => '000501',
            'nome' => 'NOME ANTIGO',
            'razao_social' => 'NOME ANTIGO',
        ]);

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'u.xlsx',
            'arquivo_path' => 'unidades-negocio/importacoes/x.xlsx',
            'status' => UnidadeNegocioImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [],
                'atualizacoes' => [[
                    'row_id' => 1,
                    'unidade_negocio_id' => $inativa->id,
                    'id_cigam' => '000501',
                    'linha' => 2,
                    'dados_atuais' => [
                        'id_cigam' => '000501',
                        'razao_social' => 'NOME ANTIGO',
                        'nome' => 'NOME ANTIGO',
                        'cpf_cnpj' => $inativa->cpf_cnpj,
                        'custo_operacional' => '0.00',
                        'possui_estoque' => false,
                        'id_estado' => Estado::ID_CEARA,
                    ],
                    'dados_novos' => [
                        'id_cigam' => '000501',
                        'razao_social' => 'NOME PLANILHA',
                        'nome' => 'NOME PLANILHA',
                        'cpf_cnpj' => $inativa->cpf_cnpj,
                        'custo_operacional' => '0.00',
                        'possui_estoque' => false,
                        'id_estado' => Estado::ID_CEARA,
                    ],
                    'campos_alterados' => [['campo' => 'nome', 'atual' => 'NOME ANTIGO', 'novo' => 'NOME PLANILHA']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.unidades-negocio.importar.confirmar', $importacao), [
                'row_ids_novas' => [],
                'row_ids_atualizacoes' => [1],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.atualizadas', 1);

        $inativa->refresh();
        $this->assertSame('NOME PLANILHA', $inativa->nome);
        $this->assertFalse($inativa->status);
    }

    public function test_usuario_nao_acessa_importacao_de_outro_usuario_mas_programador_acessa(): void
    {
        $owner = $this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_IMPORTAR]);
        $other = $this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_IMPORTAR]);
        $programador = $this->programadorUser();
        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'arquivo_original' => 'u.xlsx',
            'arquivo_path' => 'unidades-negocio/importacoes/x.xlsx',
            'status' => UnidadeNegocioImportacao::STATUS_CONCLUIDO,
            'resultado' => [],
        ]);

        $this->actingAs($other)
            ->getJson(route('admin.unidades-negocio.importar.status', $importacao))
            ->assertForbidden();

        $this->actingAs($programador)
            ->getJson(route('admin.unidades-negocio.importar.status', $importacao))
            ->assertOk()
            ->assertJsonPath('uuid', $importacao->uuid);
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'ID CIGAM', 'Razão social', 'Nome', 'CPF/CNPJ', 'Custo operacional', 'Possui estoque', 'Estado',
        ], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'unidades-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'unidades-negocio/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }
}
