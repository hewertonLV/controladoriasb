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
        Storage::disk('local')->assertExists($importacao->arquivo_path);
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
        $existenteIgual = Fruta::factory()->create([
            'id_cigam' => '9001',
            'nome' => 'BANANA IGUAL',
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '18.00',
            ...$icmsIgual,
        ]);
        $icmsAlterado = [
            'icms_ex_compra' => '0.50',
            'icms_na_compra' => '0.50',
            'um_icms' => FrutaUmIcms::KG->value,
            'icms_venda' => '7.50',
        ];
        $existenteAlterado = Fruta::factory()->create([
            'id_cigam' => '9002',
            'nome' => 'MANGA ANTIGA',
            'unidade_medicao' => FrutaUnidadeMedicao::PACOTE->value,
            'kg_por_unidade_medicao' => '5.00',
            ...$icmsAlterado,
        ]);

        $path = $this->storeSpreadsheet([
            ['9003', 'UVA NOVA', FrutaUnidadeMedicao::UNIDADE->value, '0.25', '0.00', '0.00', FrutaUmIcms::KG->value, '0.00'],
            [
                $existenteIgual->id_cigam,
                $existenteIgual->nome,
                $existenteIgual->unidade_medicao,
                '18.00',
                $icmsIgual['icms_ex_compra'],
                $icmsIgual['icms_na_compra'],
                $icmsIgual['um_icms'],
                $icmsIgual['icms_venda'],
            ],
            [
                $existenteAlterado->id_cigam,
                'MANGA ALTERADA',
                $existenteAlterado->unidade_medicao,
                '6.50',
                $icmsAlterado['icms_ex_compra'],
                $icmsAlterado['icms_na_compra'],
                $icmsAlterado['um_icms'],
                $icmsAlterado['icms_venda'],
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
        $fruta = Fruta::factory()->create([
            'id_cigam' => '9101',
            'nome' => 'ANTES',
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '10.00',
            'icms_ex_compra' => '0.00',
            'icms_na_compra' => '0.00',
            'um_icms' => FrutaUmIcms::KG->value,
            'icms_venda' => '0.00',
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
                        'icms_ex_compra' => '0.00',
                        'icms_na_compra' => '0.00',
                        'um_icms' => FrutaUmIcms::KG->value,
                        'icms_venda' => '0.00',
                    ],
                    'dados_novos' => [
                        'id_cigam' => '9101',
                        'nome' => 'DEPOIS',
                        'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
                        'kg_por_unidade_medicao' => '11.00',
                        'icms_ex_compra' => '0.00',
                        'icms_na_compra' => '0.00',
                        'um_icms' => FrutaUmIcms::KG->value,
                        'icms_venda' => '0.00',
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

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'ID CIGAM', 'Nome', 'Unidade', 'Kg', 'ICMS ex. compra', 'ICMS na compra', 'UM ICMS', 'ICMS venda %',
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
     * @return array<string, mixed>
     */
    private function importRow(
        int $rowId,
        string $idCigam,
        string $nome,
        string $unidade,
        string $kg,
        string $icmsEx = '0.00',
        string $icmsNa = '0.00',
        string $umIcms = 'KG',
        string $icmsVenda = '0.00',
    ): array {
        return [
            'row_id' => $rowId,
            'linha' => $rowId + 1,
            'dados' => [
                'id_cigam' => $idCigam,
                'nome' => mb_strtoupper($nome, 'UTF-8'),
                'unidade_medicao' => $unidade,
                'kg_por_unidade_medicao' => $kg,
                'icms_ex_compra' => $icmsEx,
                'icms_na_compra' => $icmsNa,
                'um_icms' => $umIcms,
                'icms_venda' => $icmsVenda,
            ],
        ];
    }
}
