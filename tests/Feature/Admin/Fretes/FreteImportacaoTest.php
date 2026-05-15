<?php

namespace Tests\Feature\Admin\Fretes;

use App\Enums\Permissions;
use App\Jobs\Fretes\ProcessarPreviewImportacaoFretesJob;
use App\Models\Frete;
use App\Models\FreteHistorico;
use App\Models\FreteImportacao;
use App\Models\Veiculo;
use App\Services\Fretes\FreteImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FreteImportacaoTest extends FreteTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::FRETES_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'fretes.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.fretes.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', FreteImportacao::STATUS_AGUARDANDO);

        $importacao = FreteImportacao::query()->firstOrFail();

        $this->assertSame($user->id, $importacao->user_id);
        Storage::disk('local')->assertExists($importacao->arquivo_path);
        Queue::assertPushed(ProcessarPreviewImportacaoFretesJob::class);
    }

    public function test_job_processa_nova_existente_sem_alteracao_e_com_alteracao(): void
    {
        Storage::fake('local');
        $veiculo = Veiculo::factory()->create(['id_sbs' => 7001]);
        $existenteIgual = Frete::factory()->create([
            'nome' => 'FRETE IGUAL',
            'valor' => '100.00',
            'id_veiculo' => $veiculo->id,
            'descricao' => 'Igual',
            'status_situacao' => 'ABERTA',
            'valor_fruta_kg' => '1.00',
        ]);
        $existenteAlterado = Frete::factory()->create([
            'nome' => 'FRETE ANTIGO',
            'valor' => '50.00',
            'id_veiculo' => $veiculo->id,
        ]);

        $path = $this->storeSpreadsheet([
            ['FRETE NOVO', '200', $veiculo->id_sbs, 'Nova desc', 'ABERTA', '2.50'],
            [$existenteIgual->nome, '100', $veiculo->id_sbs, 'Igual', 'ABERTA', '1'],
            [$existenteAlterado->nome, '99', $veiculo->id_sbs, 'Alterado', 'ENCERRADA', '3'],
        ]);

        $importacao = FreteImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->fretesManager()->id,
            'arquivo_original' => 'fretes.xlsx',
            'arquivo_path' => $path,
            'status' => FreteImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoFretesJob($importacao->id))
            ->handle(app(FreteImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(FreteImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(1, $importacao->atualizacoes_count);
    }

    public function test_confirmacao_cria_atualiza_com_historico(): void
    {
        $user = $this->userWithPermissions([Permissions::FRETES_IMPORTAR_CONFIRMAR]);
        $veiculo = Veiculo::factory()->create(['id_sbs' => 7101]);
        $frete = Frete::factory()->create([
            'nome' => 'ANTES',
            'id_veiculo' => $veiculo->id,
        ]);

        $importacao = FreteImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'fretes.xlsx',
            'arquivo_path' => 'fretes/importacoes/teste.xlsx',
            'status' => FreteImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    $this->importRow(1, 'CRIADO', $veiculo),
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'frete_id' => $frete->id,
                    'nome' => 'ANTES',
                    'linha' => 3,
                    'dados_atuais' => [
                        'nome' => 'ANTES',
                        'valor' => '0.00',
                        'id_veiculo' => $veiculo->id,
                        'descricao' => null,
                        'status_situacao' => 'ABERTA',
                        'valor_fruta_kg' => '0.00',
                    ],
                    'dados_novos' => [
                        'nome' => 'ANTES',
                        'valor' => '500.00',
                        'id_veiculo' => $veiculo->id,
                        'descricao' => 'Depois',
                        'status_situacao' => 'ENCERRADA',
                        'valor_fruta_kg' => '10.00',
                    ],
                    'campos_alterados' => [['campo' => 'valor', 'atual' => '0.00', 'novo' => '500.00']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.fretes.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('fretes', ['nome' => 'CRIADO']);
        $this->assertSame('500.00', $frete->fresh()->valor);
        $this->assertDatabaseHas('frete_historicos', [
            'acao' => FreteHistorico::ACAO_IMPORTACAO_CRIACAO,
        ]);
    }

    /**
     * @param  list<array{0:mixed,1:mixed,2:mixed,3:mixed,4:mixed,5:mixed}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Nome', 'Valor', 'ID SBS', 'Descrição', 'Situação', 'Valor fruta/kg'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'fretes-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'fretes/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function importRow(int $rowId, string $nome, Veiculo $veiculo): array
    {
        return [
            'row_id' => $rowId,
            'linha' => $rowId + 1,
            'dados' => [
                'nome' => mb_strtoupper($nome),
                'valor' => '100.00',
                'id_veiculo' => $veiculo->id,
                'id_sbs' => $veiculo->id_sbs,
                'descricao' => null,
                'status_situacao' => 'ABERTA',
                'valor_fruta_kg' => '1.00',
            ],
        ];
    }
}
