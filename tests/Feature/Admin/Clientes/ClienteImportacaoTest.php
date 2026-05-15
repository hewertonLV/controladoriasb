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
        $unidade = UnidadeNegocio::factory()->create();
        $praca = Praca::factory()->create([
            'nome' => 'PRACA CENTRO',
            'id_unidade_negocio' => $unidade->id,
        ]);
        Cliente::factory()->create([
            'id_cigam' => '7001',
            'razao_social' => 'Cliente Igual',
            'cnpj_cpf' => '11111111000111',
            'id_unidade_negocio' => $unidade->id,
            'id_praca' => $praca->id,
            'grupo_id' => null,
            'desconto_nf' => '0.00',
            'desconto_contrato' => '0.00',
        ]);

        $path = $this->storeSpreadsheet([
            ['7002', 'Cliente Novo', '33333333000133', $unidade->id, '1.50', '2.00', 'PRACA CENTRO', ''],
            ['7001', 'Cliente Igual', '11111111000111', $unidade->id, '0.00', '0.00', 'PRACA CENTRO', ''],
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

    /**
     * @param  list<array{0:mixed,1:mixed,2:mixed,3:mixed,4:mixed,5:mixed,6:mixed,7:mixed}>  $rows
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
                'Desconto contrato',
                'Praça',
                'Grupo',
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
