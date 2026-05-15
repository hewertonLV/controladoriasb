<?php

namespace Tests\Feature\Admin\Veiculos;

use App\Enums\Permissions;
use App\Jobs\Veiculos\ProcessarPreviewImportacaoVeiculosJob;
use App\Models\UnidadeNegocio;
use App\Models\Veiculo;
use App\Models\VeiculoHistorico;
use App\Models\VeiculoImportacao;
use App\Services\Veiculos\VeiculoImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VeiculoImportacaoTest extends VeiculoTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::VEICULOS_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'veiculos.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.veiculos.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', VeiculoImportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(ProcessarPreviewImportacaoVeiculosJob::class);
    }

    public function test_job_processa_nova_existente_sem_alteracao_e_com_alteracao(): void
    {
        Storage::fake('local');
        $unidade = UnidadeNegocio::factory()->create();

        $existenteIgual = Veiculo::factory()->create([
            'id_sbs' => 9001,
            'nome' => 'VEICULO IGUAL',
            'tipo' => 'CAMINHÃO',
            'id_unidade_negocio' => $unidade->id,
            'status' => 'ATIVO',
        ]);
        $existenteAlterado = Veiculo::factory()->create([
            'id_sbs' => 9002,
            'nome' => 'VEICULO ANTIGO',
            'tipo' => 'VAN',
            'id_unidade_negocio' => $unidade->id,
            'status' => 'ATIVO',
        ]);

        $path = $this->storeSpreadsheet([
            [9003, 'VEICULO NOVO', 'TRUCK', $unidade->id, 'ATIVO'],
            [$existenteIgual->id_sbs, $existenteIgual->nome, $existenteIgual->tipo, $unidade->id, 'ATIVO'],
            [$existenteAlterado->id_sbs, 'VEICULO ALTERADO', $existenteAlterado->tipo, $unidade->id, 'INATIVO'],
        ]);

        $importacao = VeiculoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->veiculosManager()->id,
            'arquivo_original' => 'veiculos.xlsx',
            'arquivo_path' => $path,
            'status' => VeiculoImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoVeiculosJob($importacao->id))
            ->handle(app(VeiculoImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(VeiculoImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(1, $importacao->atualizacoes_count);
    }

    public function test_confirmacao_cria_atualiza_com_historico(): void
    {
        $user = $this->userWithPermissions([Permissions::VEICULOS_IMPORTAR_CONFIRMAR]);
        $unidade = UnidadeNegocio::factory()->create();
        $veiculo = Veiculo::factory()->create([
            'id_sbs' => 9101,
            'nome' => 'ANTES',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $importacao = VeiculoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'veiculos.xlsx',
            'arquivo_path' => 'veiculos/importacoes/teste.xlsx',
            'status' => VeiculoImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [[
                    'row_id' => 1,
                    'linha' => 2,
                    'dados' => [
                        'id_sbs' => 9102,
                        'nome' => 'CRIADO',
                        'tipo' => 'VAN',
                        'id_unidade_negocio' => $unidade->id,
                        'status' => 'ATIVO',
                    ],
                ]],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'veiculo_id' => $veiculo->id,
                    'id_sbs' => 9101,
                    'linha' => 3,
                    'dados_atuais' => [
                        'id_sbs' => 9101,
                        'nome' => 'ANTES',
                        'tipo' => $veiculo->tipo,
                        'id_unidade_negocio' => $unidade->id,
                        'status' => 'ATIVO',
                    ],
                    'dados_novos' => [
                        'id_sbs' => 9101,
                        'nome' => 'DEPOIS',
                        'tipo' => $veiculo->tipo,
                        'id_unidade_negocio' => $unidade->id,
                        'status' => 'ATIVO',
                    ],
                    'campos_alterados' => [['campo' => 'nome', 'atual' => 'ANTES', 'novo' => 'DEPOIS']],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.veiculos.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('veiculos', ['id_sbs' => 9102, 'nome' => 'CRIADO']);
        $this->assertSame('DEPOIS', $veiculo->fresh()->nome);
        $this->assertDatabaseHas('veiculo_historicos', [
            'acao' => VeiculoHistorico::ACAO_IMPORTACAO_CRIACAO,
        ]);
    }

    /**
     * @param  list<array{0:int|string,1:string,2:string,3:int|string,4:string}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID SBS', 'Nome', 'Tipo', 'ID Unidade', 'Status'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'veiculos-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'veiculos/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }
}
