<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Enums\Permissions;
use App\Jobs\Frutas\ProcessarPreviewImportacaoFrutasJob;
use App\Models\Fruta;
use App\Models\FrutaHistorico;
use App\Models\FrutaImportacao;
use App\Services\Frutas\FrutaImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FrutaImportacaoTest extends FrutaTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::FRUTAS_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'frutas.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.frutas.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', FrutaImportacao::STATUS_AGUARDANDO);

        $importacao = FrutaImportacao::query()->firstOrFail();

        $this->assertSame($user->id, $importacao->user_id);
        $this->assertTrue(Storage::disk('local')->exists($importacao->arquivo_path));
        Queue::assertPushed(ProcessarPreviewImportacaoFrutasJob::class);
    }

    public function test_job_processa_nova_existente_sem_alteracao_e_com_alteracao(): void
    {
        Storage::fake('local');
        $icmsIgual = [
            'icms_ex_compra' => '1.00',
            'icms_na_compra' => '2.00',
            'um_icms' => FrutaUmIcms::UM->value,
            'icms_venda' => '12.00',
        ];
        $existenteIgual = Fruta::factory()->comIcmsCeara([
            'entrada_externo' => '1.00',
            'entrada_nacional' => '2.00',
            'entrada_um_nacional' => FrutaUmIcms::UM->value,
            'saida_nacional' => '12.00',
        ])->create([
            'id_cigam' => '9001',
            'nome' => 'BANANA IGUAL',
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '18.00',
        ]);
        $icmsAlterado = [
            'icms_ex_compra' => '0.50',
            'icms_na_compra' => '0.50',
            'um_icms' => FrutaUmIcms::KG->value,
            'icms_venda' => '7.50',
        ];
        $existenteAlterado = Fruta::factory()->comIcmsCeara([
            'entrada_externo' => '0.50',
            'entrada_nacional' => '0.50',
            'entrada_um_externo' => FrutaUmIcms::KG->value,
            'saida_nacional' => '7.50',
        ])->create([
            'id_cigam' => '9002',
            'nome' => 'MANGA ANTIGA',
            'unidade_medicao' => FrutaUnidadeMedicao::PACOTE->value,
            'kg_por_unidade_medicao' => '5.00',
        ]);

        $path = $this->storeSpreadsheet([
            ['9003', 'UVA NOVA', FrutaUnidadeMedicao::UNIDADE->value, '0.25'],
            [
                $existenteIgual->id_cigam,
                $existenteIgual->nome,
                $existenteIgual->unidade_medicao,
                '18.00',
            ],
            [
                $existenteAlterado->id_cigam,
                'MANGA ALTERADA',
                $existenteAlterado->unidade_medicao,
                '6.50',
            ],
        ]);

        $importacao = FrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->frutasManager()->id,
            'arquivo_original' => 'frutas.xlsx',
            'arquivo_path' => $path,
            'status' => FrutaImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFrutasJob($importacao->id))
            ->handle(app(FrutaImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(FrutaImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(1, $importacao->atualizacoes_count);
    }

    public function test_confirmacao_cria_atualiza_com_historico(): void
    {
        $user = $this->userWithPermissions([Permissions::FRUTAS_IMPORTAR_CONFIRMAR]);
        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_externo' => '0.00',
            'entrada_nacional' => '0.00',
            'entrada_um' => FrutaUmIcms::KG->value,
            'saida_venda' => '0.00',
        ])->create([
            'id_cigam' => '9101',
            'nome' => 'ANTES',
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $importacao = FrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'frutas.xlsx',
            'arquivo_path' => 'frutas/importacoes/teste.xlsx',
            'status' => FrutaImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    $this->importRow(1, '9102', 'CRIADA', FrutaUnidadeMedicao::SACO->value, '8.00'),
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'fruta_id' => $fruta->id,
                    'id_cigam' => '9101',
                    'linha' => 3,
                    'dados_atuais' => [
                        'id_cigam' => '9101',
                        'nome' => 'ANTES',
                        'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
                        'kg_por_unidade_medicao' => '10.00',
                    ],
                    'dados_novos' => [
                        'id_cigam' => '9101',
                        'nome' => 'DEPOIS',
                        'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
                        'kg_por_unidade_medicao' => '11.00',
                    ],
                    'campos_alterados' => [['campo' => 'nome', 'atual' => 'ANTES', 'novo' => 'DEPOIS']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.frutas.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('frutas', ['id_cigam' => '009102', 'nome' => 'CRIADA']);
        $this->assertSame('DEPOIS', $fruta->fresh()->nome);
        $this->assertDatabaseHas('fruta_historicos', [
            'acao' => FrutaHistorico::ACAO_IMPORTACAO_CRIACAO,
        ]);
    }

    public function test_job_detecta_aba_base_por_cabecalho_e_converte_abreviacoes(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheetComAbaBase([
            ['01.857.4', 'BANANA PRATA', 'CX', '14'],
            ['04.000.1', 'LARANJA SACO', 'SC', '3'],
            ['04.000.2', 'MELANCIA', 'UN', '1'],
            ['04.000.3', 'UVA PACOTE', 'PCT', '1'],
        ]);

        $importacao = FrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->frutasManager()->id,
            'arquivo_original' => 'materiais.xlsx',
            'arquivo_path' => $path,
            'status' => FrutaImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFrutasJob($importacao->id))
            ->handle(app(FrutaImportacaoProcessor::class));

        $importacao->refresh();
        $primeiraLinha = $importacao->resultado['novas'][0]['dados'];

        $this->assertSame(FrutaImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(4, $importacao->novas_count);
        $this->assertSame(0, $importacao->erros_count);
        $this->assertSame('018574', $primeiraLinha['id_cigam']);
        $this->assertSame(FrutaUnidadeMedicao::CAIXA->value, $primeiraLinha['unidade_medicao']);
        $this->assertSame('14.00', $primeiraLinha['kg_por_unidade_medicao']);

        $linhaUvaPct = collect($importacao->resultado['novas'] ?? [])
            ->first(fn (array $item) => ($item['dados']['id_cigam'] ?? '') === '040003');

        $this->assertNotNull($linhaUvaPct);
        $this->assertSame(FrutaUnidadeMedicao::PCT->value, $linhaUvaPct['dados']['unidade_medicao']);
        $this->assertSame('1.000', $linhaUvaPct['dados']['kg_por_unidade_medicao']);
    }

    public function test_job_importa_fruta_com_um_kg(): void
    {
        Storage::fake('local');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID CIGAM', 'Nome', 'KG', 'Kg por unidade'], null, 'A1');
        $sheet->fromArray(['004297', 'FRUTA KG', 'KG', '1'], null, 'A2');

        $tmp = tempnam(sys_get_temp_dir(), 'frutas-kg-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'frutas/importacoes/teste-kg.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        $importacao = FrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->frutasManager()->id,
            'arquivo_original' => 'frutas-kg.xlsx',
            'arquivo_path' => $path,
            'status' => FrutaImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFrutasJob($importacao->id))
            ->handle(app(FrutaImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(FrutaImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(0, $importacao->erros_count);
        $this->assertCount(1, $importacao->resultado['novas'] ?? []);
        $this->assertSame(FrutaUnidadeMedicao::KG->value, $importacao->resultado['novas'][0]['dados']['unidade_medicao']);
        $this->assertSame('1.000', $importacao->resultado['novas'][0]['dados']['kg_por_unidade_medicao']);
    }

    public function test_job_ignora_id_cigam_repetido_com_dados_iguais_e_bloqueia_divergente(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['100', 'BANANA', FrutaUnidadeMedicao::CAIXA->value, '14'],
            ['100', 'BANANA', FrutaUnidadeMedicao::CAIXA->value, '14'],
            ['100', 'BANANA DIFERENTE', FrutaUnidadeMedicao::CAIXA->value, '14'],
        ]);

        $importacao = FrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->frutasManager()->id,
            'arquivo_original' => 'frutas.xlsx',
            'arquivo_path' => $path,
            'status' => FrutaImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFrutasJob($importacao->id))
            ->handle(app(FrutaImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->erros_count);
        $this->assertSame('ID CIGAM duplicado na planilha (já aparece na linha 2).', $importacao->resultado['erros'][0]['erros'][0]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'ID CIGAM', 'Nome', 'Unidade', 'Kg',
        ], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'frutas-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'frutas/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    /**
     * @param  list<list<mixed>>  $baseRows
     */
    private function storeSpreadsheetComAbaBase(array $baseRows): string
    {
        $spreadsheet = new Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('T.O');
        $sheet->fromArray(['TP OPERACAO', 'DESC TIPO OPERACAO'], null, 'A1');
        $sheet->fromArray(['610.1A', 'VENDA'], null, 'A2');

        $base = $spreadsheet->createSheet();
        $base->setTitle('BASE');
        $base->fromArray(['CODIGO MATERIAL', 'DESCRICAO MAT', 'UNID MEDIDA', 'PESO MAT'], null, 'A1');

        foreach ($baseRows as $index => $row) {
            $base->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'frutas-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'frutas/importacoes/teste-base.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function importRow(
        int $rowId,
        string $idCigam,
        string $nome,
        string $unidade,
        string $kg,
    ): array {
        return [
            'row_id' => $rowId,
            'linha' => $rowId + 1,
            'dados' => [
                'id_cigam' => $idCigam,
                'nome' => mb_strtoupper($nome, 'UTF-8'),
                'unidade_medicao' => $unidade,
                'kg_por_unidade_medicao' => $kg,
            ],
        ];
    }
}
