<?php

namespace Tests\Feature\Admin\Pracas;

use App\Enums\Permissions;
use App\Jobs\Pracas\ProcessarPreviewImportacaoPracasJob;
use App\Models\Praca;
use App\Models\PracaHistorico;
use App\Models\PracaImportacao;
use App\Models\UnidadeNegocio;
use App\Services\Pracas\PracaImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PracaImportacaoTest extends PracaTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::PRACAS_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'pracas.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.pracas.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', PracaImportacao::STATUS_AGUARDANDO);

        $importacao = PracaImportacao::query()->firstOrFail();

        $this->assertSame($user->id, $importacao->user_id);
        Storage::disk('local')->assertExists($importacao->arquivo_path);
        Queue::assertPushed(ProcessarPreviewImportacaoPracasJob::class);
    }

    public function test_job_processa_nova_existente_sem_alteracao_e_com_alteracao(): void
    {
        Storage::fake('local');
        $unidade = UnidadeNegocio::factory()->create();

        $existenteIgual = Praca::factory()->create([
            'nome' => 'PRACA IGUAL',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $path = $this->storeSpreadsheet([
            ['PRACA NOVA', $unidade->id],
            [$existenteIgual->nome, $unidade->id],
        ]);

        $importacao = PracaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->pracasManager()->id,
            'arquivo_original' => 'pracas.xlsx',
            'arquivo_path' => $path,
            'status' => PracaImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoPracasJob($importacao->id))
            ->handle(app(PracaImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(PracaImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(0, $importacao->atualizacoes_count);
    }

    public function test_confirmacao_cria_atualiza_com_historico(): void
    {
        $user = $this->userWithPermissions([Permissions::PRACAS_IMPORTAR_CONFIRMAR]);
        $unidade = UnidadeNegocio::factory()->create();
        $praca = Praca::factory()->create([
            'nome' => 'ANTES',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $importacao = PracaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'pracas.xlsx',
            'arquivo_path' => 'pracas/importacoes/teste.xlsx',
            'status' => PracaImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    $this->importRow(1, 'CRIADA', $unidade->id),
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'praca_id' => $praca->id,
                    'nome' => 'ANTES',
                    'id_unidade_negocio' => $unidade->id,
                    'linha' => 3,
                    'dados_atuais' => [
                        'nome' => 'ANTES',
                        'id_unidade_negocio' => $unidade->id,
                    ],
                    'dados_novos' => [
                        'nome' => 'DEPOIS',
                        'id_unidade_negocio' => $unidade->id,
                    ],
                    'campos_alterados' => [['campo' => 'nome', 'atual' => 'ANTES', 'novo' => 'DEPOIS']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.pracas.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('pracas', ['nome' => 'CRIADA', 'id_unidade_negocio' => $unidade->id]);
        $this->assertSame('DEPOIS', $praca->fresh()->nome);
        $this->assertDatabaseHas('praca_historicos', [
            'acao' => PracaHistorico::ACAO_IMPORTACAO_CRIACAO,
        ]);
    }

    /**
     * @param  list<array{0:mixed,1:mixed}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Nome', 'ID Unidade'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'pracas-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'pracas/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function importRow(int $rowId, string $nome, int $idUnidade): array
    {
        return [
            'row_id' => $rowId,
            'linha' => $rowId + 1,
            'dados' => [
                'nome' => mb_strtoupper($nome, 'UTF-8'),
                'id_unidade_negocio' => $idUnidade,
            ],
        ];
    }
}
