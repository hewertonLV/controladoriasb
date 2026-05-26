<?php

namespace Tests\Feature\Admin\UnidadesNegocio;

use App\Enums\Permissions;
use App\Jobs\UnidadesNegocio\ProcessarPreviewImportacaoUnidadesNegocioJob;
use App\Models\Cliente;
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
            $this->linhaPlanilha(['000103', 'UNIDADE NOVA', 'UNIDADE NOVA', '33333333000133', '100.00']),
            $this->linhaPlanilha([
                $existenteIgual->id_cigam,
                $existenteIgual->razao_social,
                $existenteIgual->nome,
                $existenteIgual->cpf_cnpj,
                number_format((float) $existenteIgual->custo_operacional, 2, '.', ''),
                'NÃO',
                'NÃO',
                'NÃO',
                'NÃO',
                'SIM',
            ]),
            $this->linhaPlanilha([
                $existenteAlterada->id_cigam,
                'RAZAO NOVA',
                'NOME NOVO',
                $existenteAlterada->cpf_cnpj,
                '50.00',
                'NÃO',
                'NÃO',
                'NÃO',
                'NÃO',
                'SIM',
            ]),
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
        $this->assertFalse($importacao->resultado['novas'][0]['dados']['emite_nota_fiscal']);
        $this->assertSame('000101', $importacao->resultado['sem_alteracoes'][0]['id_cigam']);
        $this->assertSame('000102', $importacao->resultado['atualizacoes'][0]['id_cigam']);
    }

    public function test_importacao_colunas_booleanas_vazias_usam_padrao_nao(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['000108', 'UNIDADE FLAGS VAZIAS', 'UNIDADE FLAGS VAZIAS', '', '0.00', '', '', '', '', '', 'CEARA'],
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

        $dados = $importacao->resultado['novas'][0]['dados'];
        $this->assertFalse($dados['possui_estoque']);
        $this->assertFalse($dados['is_unidade_producao']);
        $this->assertFalse($dados['is_hub']);
        $this->assertFalse($dados['is_galpao_operacional']);
        $this->assertFalse($dados['emite_nota_fiscal']);
    }

    public function test_importacao_rejeita_galpao_sem_estoque(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            $this->linhaPlanilha(['000109', 'GALPAO SEM ESTOQUE', 'GALPAO SEM ESTOQUE', '', '0.00', 'NÃO', 'NÃO', 'NÃO', 'SIM', 'NÃO', 'CEARA']),
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

        $this->assertSame(1, $importacao->erros_count);
        $this->assertStringContainsString('exige Controle estoque', $importacao->resultado['erros'][0]['erros'][0]);
    }

    public function test_importacao_resolve_estado_por_nome(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            $this->linhaPlanilha(['000104', 'UNIDADE CEARA', 'UNIDADE CEARA', '33333333000133', '100.00', 'NÃO', 'NÃO', 'NÃO', 'NÃO', 'NÃO', ' Ceará ']),
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
            $this->linhaPlanilha(['000105', 'UNIDADE DOC 1', 'UNIDADE DOC 1', '11.222.333/0001-81', '100.00']),
            $this->linhaPlanilha(['000106', 'UNIDADE DOC 2', 'UNIDADE DOC 2', '11222333000181', '100.00']),
            $this->linhaPlanilha(['000107', 'UNIDADE SEM DOC', 'UNIDADE SEM DOC', '', '100.00']),
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
                        'dados' => $this->dadosImportacao([
                            'id_cigam' => '000202',
                            'razao_social' => 'CRIADA',
                            'nome' => 'CRIADA',
                            'cpf_cnpj' => '55555555000155',
                            'custo_operacional' => '25.00',
                        ]),
                    ],
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'unidade_negocio_id' => $existente->id,
                    'id_cigam' => '000201',
                    'linha' => 3,
                    'dados_atuais' => $this->dadosImportacao([
                        'id_cigam' => '000201',
                        'razao_social' => 'ANTES',
                        'nome' => 'ANTES',
                        'cpf_cnpj' => $existente->cpf_cnpj,
                        'custo_operacional' => '0.00',
                    ]),
                    'dados_novos' => $this->dadosImportacao([
                        'id_cigam' => '000201',
                        'razao_social' => 'DEPOIS',
                        'nome' => 'DEPOIS',
                        'cpf_cnpj' => $existente->cpf_cnpj,
                        'custo_operacional' => '0.00',
                    ]),
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

        $this->assertDatabaseHas('unidades_negocio', [
            'id_cigam' => '000202',
            'nome' => 'CRIADA',
            'emite_nota_fiscal' => false,
        ]);
        $this->assertSame('DEPOIS', $existente->fresh()->nome);
    }

    public function test_job_detecta_atualizacao_de_codigo_cliente(): void
    {
        Storage::fake('local');
        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '000401',
            'nome' => 'LOJA COM CLIENTE',
            'razao_social' => 'LOJA COM CLIENTE',
        ]);
        $cliente = Cliente::factory()->create([
            'id_cigam' => '000402',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $path = $this->storeSpreadsheet([
            $this->linhaPlanilha([
                $unidade->id_cigam,
                $unidade->razao_social,
                $unidade->nome,
                $unidade->cpf_cnpj,
                number_format((float) $unidade->custo_operacional, 2, '.', ''),
                'NÃO',
                'NÃO',
                'NÃO',
                'NÃO',
                'SIM',
                'CEARA',
                '402',
            ]),
        ]);

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->unidadesNegocioManager()->id,
            'arquivo_original' => 'u.xlsx',
            'arquivo_path' => $path,
            'status' => UnidadeNegocioImportacao::STATUS_AGUARDANDO,
        ]);

        (new UnidadeNegocioImportacaoProcessor)->processar($importacao->fresh());

        $importacao->refresh();
        $atualizacoes = $importacao->resultado['atualizacoes'] ?? [];
        $this->assertCount(1, $atualizacoes);
        $this->assertSame($cliente->id, $atualizacoes[0]['dados_novos']['id_cliente'] ?? null);
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
                        'dados' => $this->dadosImportacao([
                            'id_cigam' => '000302',
                            'razao_social' => 'CRIADA DOC REPETIDO',
                            'nome' => 'CRIADA DOC REPETIDO',
                            'cpf_cnpj' => '11222333000181',
                            'custo_operacional' => '25.00',
                        ]),
                    ],
                    [
                        'row_id' => 2,
                        'linha' => 3,
                        'dados' => $this->dadosImportacao([
                            'id_cigam' => '000303',
                            'razao_social' => 'CRIADA SEM DOC',
                            'nome' => 'CRIADA SEM DOC',
                            'cpf_cnpj' => null,
                            'custo_operacional' => '25.00',
                        ]),
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
                    'dados_atuais' => $this->dadosImportacao([
                        'id_cigam' => '000501',
                        'razao_social' => 'NOME ANTIGO',
                        'nome' => 'NOME ANTIGO',
                        'cpf_cnpj' => $inativa->cpf_cnpj,
                        'custo_operacional' => '0.00',
                    ]),
                    'dados_novos' => $this->dadosImportacao([
                        'id_cigam' => '000501',
                        'razao_social' => 'NOME PLANILHA',
                        'nome' => 'NOME PLANILHA',
                        'cpf_cnpj' => $inativa->cpf_cnpj,
                        'custo_operacional' => '0.00',
                    ]),
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
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function dadosImportacao(array $override = []): array
    {
        return array_merge([
            'id_cigam' => '000001',
            'razao_social' => 'RAZAO',
            'nome' => 'NOME',
            'cpf_cnpj' => null,
            'custo_operacional' => '0.00',
            'possui_estoque' => false,
            'is_unidade_producao' => false,
            'is_hub' => false,
            'is_galpao_operacional' => false,
            'emite_nota_fiscal' => false,
            'id_estado' => Estado::ID_CEARA,
        ], $override);
    }

    /**
     * Monta linha completa da planilha (colunas A–K). Flags omitidas = NÃO; estado omitido = CEARA.
     *
     * @param  list<string|null>  $prefixo  [A..E] obrigatórios; F–J e K opcionais
     * @return list<string|null>
     */
    private function linhaPlanilha(array $prefixo): array
    {
        if (count($prefixo) >= 13) {
            return array_slice($prefixo, 0, 13);
        }

        if (count($prefixo) >= 12) {
            return array_slice($prefixo, 0, 12);
        }

        if (count($prefixo) >= 11) {
            return array_slice($prefixo, 0, 11);
        }

        $flags = array_slice($prefixo, 5, 5);
        while (count($flags) < 5) {
            $flags[] = 'NÃO';
        }

        $estado = $prefixo[10] ?? 'CEARA';

        return [
            $prefixo[0],
            $prefixo[1],
            $prefixo[2],
            $prefixo[3] ?? '',
            $prefixo[4] ?? '0.00',
            $flags[0],
            $flags[1],
            $flags[2],
            $flags[3],
            $flags[4],
            $estado,
        ];
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'ID CIGAM',
            'Razão social',
            'Nome',
            'CPF/CNPJ',
            'Custo operacional',
            'Controle estoque frutas',
            'Unidade produção',
            'Unidade HUB',
            'Galpão operacional',
            'Emite nota fiscal',
            'Estado',
            'Código do cliente',
            'Centro armazenagem',
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
