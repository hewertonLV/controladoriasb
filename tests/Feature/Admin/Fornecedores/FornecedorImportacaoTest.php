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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
        Storage::disk('local')->assertExists($importacao->arquivo_path);
        Queue::assertPushed(ProcessarPreviewImportacaoFornecedoresJob::class);
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
