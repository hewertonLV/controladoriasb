<?php

namespace Tests\Feature\Admin\Grupos;

use App\Enums\Permissions;
use App\Jobs\Grupos\ProcessarPreviewImportacaoGruposJob;
use App\Models\Grupo;
use App\Models\GrupoHistorico;
use App\Models\GrupoImportacao;
use App\Services\Grupos\GrupoImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GrupoImportacaoTest extends GrupoTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::GRUPOS_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'grupos.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.grupos.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', GrupoImportacao::STATUS_AGUARDANDO);

        $importacao = GrupoImportacao::query()->firstOrFail();

        $this->assertSame($user->id, $importacao->user_id);
        Storage::disk('local')->assertExists($importacao->arquivo_path);
        Queue::assertPushed(ProcessarPreviewImportacaoGruposJob::class);
    }

    public function test_job_processa_nova_existente_sem_alteracao(): void
    {
        Storage::fake('local');
        $existenteIgual = Grupo::factory()->create(['nome' => 'GRUPO IGUAL']);

        $path = $this->storeSpreadsheet([
            ['Grupo Novo'],
            [$existenteIgual->nome],
            ['Outro Grupo'],
        ]);

        $importacao = GrupoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->gruposManager()->id,
            'arquivo_original' => 'grupos.xlsx',
            'arquivo_path' => $path,
            'status' => GrupoImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoGruposJob($importacao->id))
            ->handle(app(GrupoImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(GrupoImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(2, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(0, $importacao->atualizacoes_count);
    }

    public function test_confirmacao_cria_atualiza_com_historico(): void
    {
        $user = $this->userWithPermissions([Permissions::GRUPOS_IMPORTAR_CONFIRMAR]);
        $grupo = Grupo::factory()->create(['nome' => 'ANTES']);

        $importacao = GrupoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'grupos.xlsx',
            'arquivo_path' => 'grupos/importacoes/teste.xlsx',
            'status' => GrupoImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    $this->importRow(1, 'CRIADO'),
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'grupo_id' => $grupo->id,
                    'nome' => 'ANTES',
                    'linha' => 3,
                    'dados_atuais' => ['nome' => 'ANTES'],
                    'dados_novos' => ['nome' => 'DEPOIS'],
                    'campos_alterados' => [['campo' => 'nome', 'atual' => 'ANTES', 'novo' => 'DEPOIS']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.grupos.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('grupos', ['nome' => 'CRIADO']);
        $this->assertSame('DEPOIS', $grupo->fresh()->nome);
        $this->assertDatabaseHas('grupo_historicos', [
            'acao' => GrupoHistorico::ACAO_IMPORTACAO_CRIACAO,
        ]);
    }

    /**
     * @param  list<array{0:mixed}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Nome'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'grupos-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'grupos/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function importRow(int $rowId, string $nome): array
    {
        return [
            'row_id' => $rowId,
            'linha' => $rowId + 1,
            'dados' => [
                'nome' => mb_strtoupper($nome),
            ],
        ];
    }
}
