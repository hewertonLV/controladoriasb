<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\Permissions;
use App\Jobs\Captacao\ProcessarPreviewImportacaoClienteFrutasJob;
use App\Models\Captacao\ClienteFrutaImportacao;
use App\Models\Fruta;
use App\Services\Captacao\ClienteFrutaVinculoImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ClienteFrutaVinculoImportacaoTest extends CaptacaoTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_CLIENTE_FRUTA_VINCULAR]);

        $arquivo = UploadedFile::fake()->create(
            'vinculos.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.captacao.frutas-por-loja.importar.iniciar'), [
                'arquivo' => $arquivo,
                'faturamento' => $c['faturamento']->id,
            ])
            ->assertAccepted()
            ->assertJsonPath('status', ClienteFrutaImportacao::STATUS_AGUARDANDO);

        $importacao = ClienteFrutaImportacao::query()->firstOrFail();
        $this->assertSame($c['faturamento']->id, $importacao->id_unidade_negocio_faturamento);

        Queue::assertPushed(ProcessarPreviewImportacaoClienteFrutasJob::class);
    }

    public function test_job_processa_novo_vinculo_ja_vinculado_e_erro(): void
    {
        Storage::fake('local');
        $c = $this->cenarioCaptacaoBasico();

        $fruta2 = Fruta::factory()->create(['nome' => 'MANGA IMPORT TESTE']);

        $path = $this->storeSpreadsheet([
            [$c['cliente']->razao_social, $fruta2->nome],
            [$c['cliente']->razao_social, $c['fruta']->nome],
            ['LOJA INEXISTENTE XYZ', $fruta2->nome],
        ]);

        $importacao = ClienteFrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->captacaoManager()->id,
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'arquivo_original' => 'vinculos.xlsx',
            'arquivo_path' => $path,
            'status' => ClienteFrutaImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoClienteFrutasJob($importacao->id))
            ->handle(app(ClienteFrutaVinculoImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(ClienteFrutaImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(1, $importacao->erros_count);
    }

    public function test_confirmacao_cria_vinculos_sem_remover_existentes(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_CLIENTE_FRUTA_VINCULAR]);
        $fruta2 = Fruta::factory()->create(['nome' => 'UVA IMPORT TESTE']);

        $importacao = ClienteFrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'arquivo_original' => 'vinculos.xlsx',
            'arquivo_path' => 'captacao/cliente-frutas/importacoes/teste.xlsx',
            'status' => ClienteFrutaImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    [
                        'row_id' => 1,
                        'linha' => 2,
                        'id_cliente' => $c['cliente']->id,
                        'id_fruta' => $fruta2->id,
                        'cliente_nome' => $c['cliente']->razao_social,
                        'fruta_nome' => $fruta2->nome,
                        'dados' => ['loja' => $c['cliente']->razao_social, 'fruta' => $fruta2->nome],
                    ],
                ],
                'atualizacoes' => [],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.frutas-por-loja.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.vinculos_criados', 1);

        $this->assertDatabaseHas('cliente_fruta_vinculos', [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $c['fruta']->id,
            'ativo' => true,
        ]);
        $this->assertDatabaseHas('cliente_fruta_vinculos', [
            'id_cliente' => $c['cliente']->id,
            'id_fruta' => $fruta2->id,
            'ativo' => true,
        ]);
    }

    public function test_tela_importar_visivel_com_permissao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_CLIENTE_FRUTA_VINCULAR]);

        $this->actingAs($user)
            ->get(route('admin.captacao.frutas-por-loja.importar', ['faturamento' => $c['faturamento']->id]))
            ->assertOk()
            ->assertSee('Importação por planilha Excel', false)
            ->assertSee('fruta_loja_vinculo.xlsx', false)
            ->assertSee('Razão social ou nome da loja', false);
    }

    /**
     * @param  list<array{0:string, 1:string}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Loja', 'Fruta'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'fruta-loja-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'captacao/cliente-frutas/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }
}
