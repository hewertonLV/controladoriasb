<?php

namespace Tests\Feature\Admin\Clientes;

use App\Enums\Permissions;
use App\Jobs\Clientes\ProcessarPreviewImportacaoClientesJob;
use App\Models\Cliente;
use App\Models\ClienteImportacao;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use App\Services\Clientes\ClienteImportacaoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ClienteImportacaoTest extends ClienteTestCase
{
    public function test_usuario_com_permissao_inicia_importacao_e_despacha_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->userWithPermissions([Permissions::CLIENTES_IMPORTAR]);
        $arquivo = UploadedFile::fake()->create(
            'clientes.xlsx',
            10,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->actingAs($user)
            ->postJson(route('admin.clientes.importar.iniciar'), ['arquivo' => $arquivo])
            ->assertAccepted()
            ->assertJsonPath('status', ClienteImportacao::STATUS_AGUARDANDO);

        Queue::assertPushed(ProcessarPreviewImportacaoClientesJob::class);
    }

    public function test_job_processa_planilha_valida(): void
    {
        Storage::fake('local');
        $unidade = UnidadeNegocio::factory()->create(['id_cigam' => '000110']);
        $praca = Praca::factory()->create([
            'nome' => 'PRACA CENTRO',
            'id_unidade_negocio' => $unidade->id,
        ]);
        Cliente::factory()->create([
            'id_cigam' => '7001',
            'razao_social' => 'Cliente Igual',
            'fantasia' => null,
            'cnpj_cpf' => '11111111000111',
            'id_unidade_negocio' => $unidade->id,
            'id_praca' => $praca->id,
            'grupo_id' => null,
            'desconto_nf' => '0.00',
        ]);

        $path = $this->storeSpreadsheet([
            ['7002', 'Cliente Novo', '33333333000133', '110', '1.50', 'PRACA CENTRO', ''],
            ['7001', 'Cliente Igual', '11111111000111', '000110', '0.00', 'PRACA CENTRO', ''],
        ]);

        $importacao = ClienteImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->clientesManager()->id,
            'arquivo_original' => 'clientes.xlsx',
            'arquivo_path' => $path,
            'status' => ClienteImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoClientesJob($importacao->id))
            ->handle(app(ClienteImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(ClienteImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(1, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
    }

    public function test_importacao_permite_cnpj_cpf_duplicado_entre_clientes(): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions([
            Permissions::CLIENTES_IMPORTAR,
            Permissions::CLIENTES_IMPORTAR_CONFIRMAR,
        ]);
        $unidade = UnidadeNegocio::factory()->create(['id_cigam' => '000110']);
        Praca::factory()->create([
            'nome' => 'PRACA CENTRO',
            'id_unidade_negocio' => $unidade->id,
        ]);
        Cliente::factory()->create([
            'id_cigam' => '7001',
            'razao_social' => 'Cliente Existente',
            'cnpj_cpf' => '11111111000111',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $path = $this->storeSpreadsheet([
            ['7002', 'Outro Cliente Mesmo Doc', '11111111000111', '110', '0.00', 'PRACA CENTRO', ''],
        ]);

        $importacao = ClienteImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'clientes.xlsx',
            'arquivo_path' => $path,
            'status' => ClienteImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoClientesJob($importacao->id))
            ->handle(app(ClienteImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(ClienteImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(0, $importacao->erros_count);
        $this->assertSame(1, $importacao->novas_count);

        $this->actingAs($user)
            ->postJson(route('admin.clientes.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.erros', []);

        $this->assertSame(2, Cliente::query()->where('cnpj_cpf', '11111111000111')->count());
        $this->assertDatabaseHas('clientes', [
            'id_cigam' => '007002',
            'cnpj_cpf' => '11111111000111',
        ]);
    }

    public function test_importacao_resolve_unidade_por_id_cigam_normalizado(): void
    {
        Storage::fake('local');
        $unidade = UnidadeNegocio::factory()->create(['id_cigam' => '000110']);
        Praca::factory()->create([
            'nome' => 'PRACA CENTRO',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $path = $this->storeSpreadsheet([
            ['7004', 'Cliente Unidade Cigam', '33333333000133', ' 1-10 ', '1.50', 'PRACA CENTRO', ''],
        ]);

        $importacao = ClienteImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->clientesManager()->id,
            'arquivo_original' => 'clientes.xlsx',
            'arquivo_path' => $path,
            'status' => ClienteImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoClientesJob($importacao->id))
            ->handle(app(ClienteImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(0, $importacao->erros_count);
        $this->assertSame($unidade->id, $importacao->resultado['novas'][0]['dados']['id_unidade_negocio']);
    }

    public function test_importacao_salva_fantasia(): void
    {
        Storage::fake('local');
        $user = $this->userWithPermissions([
            Permissions::CLIENTES_IMPORTAR,
            Permissions::CLIENTES_IMPORTAR_CONFIRMAR,
        ]);
        $unidade = UnidadeNegocio::factory()->create(['id_cigam' => '000111']);
        Praca::factory()->create([
            'nome' => 'PRACA CENTRO',
            'id_unidade_negocio' => $unidade->id,
        ]);

        $path = $this->storeSpreadsheet([
            ['7003', 'Cliente Importado', '33333333000133', '111', '1.50', 'PRACA CENTRO', '', '  fantasia   importada  '],
        ]);

        $importacao = ClienteImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'clientes.xlsx',
            'arquivo_path' => $path,
            'status' => ClienteImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoClientesJob($importacao->id))
            ->handle(app(ClienteImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame('FANTASIA IMPORTADA', $importacao->resultado['novas'][0]['dados']['fantasia']);

        $this->actingAs($user)
            ->postJson(route('admin.clientes.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1);

        $this->assertDatabaseHas('clientes', [
            'id_cigam' => '007003',
            'fantasia' => 'FANTASIA IMPORTADA',
        ]);
    }

    /**
     * @param  list<array{0:mixed,1:mixed,2:mixed,3:mixed,4:mixed,5:mixed,6:mixed,7?:mixed}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            [
                'ID CIGAM',
                'Razão social',
                'CPF/CNPJ',
                'UN',
                'Desconto NF',
                'Praça',
                'Grupo',
                'Fantasia',
            ],
            null,
            'A1',
        );

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'clientes-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'clientes/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }
}
