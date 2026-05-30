<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\Permissions;
use App\Jobs\Captacao\ProcessarPreviewImportacaoCaptacaoCarteiraJob;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoCarteiraImportacao;
use App\Models\Cliente;
use App\Services\Captacao\CaptacaoCarteiraImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CaptacaoCarteiraImportacaoTest extends CaptacaoTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]);

        $arquivo = UploadedFile::fake()->create(
            'lojas.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.captacao.carteiras.importar-lojas.iniciar', $c['carteira']), [
                'arquivo' => $arquivo,
            ])
            ->assertAccepted()
            ->assertJsonPath('status', CaptacaoCarteiraImportacao::STATUS_AGUARDANDO);

        $importacao = CaptacaoCarteiraImportacao::query()->firstOrFail();
        $this->assertSame($c['carteira']->id, $importacao->id_captacao_carteira);

        Queue::assertPushed(ProcessarPreviewImportacaoCaptacaoCarteiraJob::class);
    }

    public function test_job_processa_nova_loja_ja_vinculada_e_erro(): void
    {
        Storage::fake('local');
        $c = $this->cenarioCaptacaoBasico();

        $clienteNovo = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => null,
            'razao_social' => 'LOJA NOVA IMPORT TESTE',
        ]);

        $path = $this->storeSpreadsheet([
            [$clienteNovo->id_cigam],
            [$c['cliente']->id_cigam],
            ['999999'],
        ]);

        $importacao = CaptacaoCarteiraImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->captacaoManager()->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'arquivo_original' => 'lojas.xlsx',
            'arquivo_path' => $path,
            'status' => CaptacaoCarteiraImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoCaptacaoCarteiraJob($importacao->id))
            ->handle(app(CaptacaoCarteiraImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(CaptacaoCarteiraImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(1, $importacao->erros_count);
    }

    public function test_job_rejeita_cliente_em_outra_carteira(): void
    {
        Storage::fake('local');
        $c = $this->cenarioCaptacaoBasico();

        $outraCarteira = CaptacaoCarteira::query()->create([
            'nome' => 'Outra Carteira Import',
            'id_unidade_negocio_faturamento' => $c['faturamento']->id,
            'id_unidade_negocio_galpao' => $c['galpao']->id,
            'ativo' => true,
        ]);

        $clienteOutra = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => $outraCarteira->id,
            'razao_social' => 'LOJA OUTRA CARTEIRA TESTE',
        ]);

        $path = $this->storeSpreadsheet([[$clienteOutra->id_cigam]]);

        $importacao = CaptacaoCarteiraImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->captacaoManager()->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'arquivo_original' => 'lojas.xlsx',
            'arquivo_path' => $path,
            'status' => CaptacaoCarteiraImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoCaptacaoCarteiraJob($importacao->id))
            ->handle(app(CaptacaoCarteiraImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(1, $importacao->erros_count);
        $this->assertSame(0, $importacao->novas_count);
    }

    public function test_confirmacao_vincula_lojas_sem_remover_existentes(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]);

        $clienteNovo = Cliente::factory()->create([
            'id_unidade_negocio' => $c['faturamento']->id,
            'id_captacao_carteira' => null,
            'razao_social' => 'LOJA CONFIRM IMPORT TESTE',
        ]);

        $importacao = CaptacaoCarteiraImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'id_captacao_carteira' => $c['carteira']->id,
            'arquivo_original' => 'lojas.xlsx',
            'arquivo_path' => 'captacao/carteiras/importacoes/teste.xlsx',
            'status' => CaptacaoCarteiraImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    [
                        'row_id' => 1,
                        'linha' => 2,
                        'id_cliente' => $clienteNovo->id,
                        'cliente_nome' => $clienteNovo->razao_social,
                        'codigo' => $clienteNovo->id_cigam,
                        'dados' => ['id_cigam_cliente' => $clienteNovo->id_cigam],
                    ],
                ],
                'atualizacoes' => [],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.captacao.carteiras.importar-lojas.confirmar', [$c['carteira'], $importacao]), [
                'row_ids_novas' => [1],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.lojas_vinculadas', 1);

        $this->assertDatabaseHas('clientes', [
            'id' => $c['cliente']->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
        $this->assertDatabaseHas('clientes', [
            'id' => $clienteNovo->id,
            'id_captacao_carteira' => $c['carteira']->id,
        ]);
    }

    public function test_tela_importar_visivel_com_permissao(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]);

        $this->actingAs($user)
            ->get(route('admin.captacao.carteiras.importar-lojas', $c['carteira']))
            ->assertOk()
            ->assertSee('Importação por planilha Excel', false)
            ->assertSee('carteira_lojas_vinculo.xlsx', false)
            ->assertSee('ID CIGAM do cliente', false);
    }

    public function test_editar_carteira_exibe_botao_importar_lojas(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]);

        $this->actingAs($user)
            ->get(route('admin.captacao.carteiras.edit', $c['carteira']))
            ->assertOk()
            ->assertSee('Importar lojas (Excel)', false);
    }

    /**
     * @param  list<array{0:string}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['id_cigam_cliente'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'carteira-lojas-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'captacao/carteiras/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }
}
