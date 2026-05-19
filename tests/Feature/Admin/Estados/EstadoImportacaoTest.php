<?php

namespace Tests\Feature\Admin\Estados;

use App\Enums\Permissions;
use App\Jobs\Estados\ProcessarPreviewImportacaoEstadosJob;
use App\Models\Estado;
use App\Models\EstadoImportacao;
use App\Services\Estados\EstadoImportacaoProcessor;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class EstadoImportacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoSeeder::class);
    }

    public function test_usuario_com_permissao_acessa_tela_de_importacao(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::ESTADOS_IMPORTAR]))
            ->get(route('admin.estados.importar'))
            ->assertOk()
            ->assertSee('form-iniciar', false)
            ->assertSee('id="arquivo"', false)
            ->assertSee('ID CIGAM', false);
    }

    public function test_job_processa_nova_existente_sem_alteracao(): void
    {
        Storage::fake('local');

        $path = $this->storeSpreadsheet([
            ['000099', 'TOCANTINS', 'TO', 'PAGA ICMS'],
            ['000001', 'CEARA', 'CE', 'PAGA ICMS NA ENTRADA DO ESTADO'],
            ['000098', 'GOIAS', 'GO', null],
        ]);

        $importacao = EstadoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'estados.xlsx',
            'arquivo_path' => $path,
            'status' => EstadoImportacao::STATUS_AGUARDANDO,
        ]);

        (new ProcessarPreviewImportacaoEstadosJob($importacao->id))
            ->handle(app(EstadoImportacaoProcessor::class));

        $importacao->refresh();

        $this->assertSame(EstadoImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertSame(2, $importacao->novas_count);
        $this->assertSame(1, $importacao->sem_alteracoes_count);
        $this->assertSame(0, $importacao->atualizacoes_count);
    }

    public function test_confirmacao_cria_e_atualiza_estado(): void
    {
        $user = $this->userWithPermissions([Permissions::ESTADOS_IMPORTAR_CONFIRMAR]);
        $estado = Estado::query()->where('abreviacao', 'CE')->firstOrFail();

        $importacao = EstadoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'estados.xlsx',
            'arquivo_path' => 'estados/importacoes/teste.xlsx',
            'status' => EstadoImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [
                    $this->importRow(1, '000014', 'MATO GROSSO', 'MT', 'TESTE'),
                ],
                'atualizacoes' => [[
                    'row_id' => 2,
                    'estado_id' => $estado->id,
                    'id_cigam' => '000001',
                    'abreviacao' => 'CE',
                    'nome' => 'CEARA',
                    'linha' => 3,
                    'dados_atuais' => [
                        'id_cigam' => '000001',
                        'nome' => 'CEARA',
                        'abreviacao' => 'CE',
                        'descricao' => 'PAGA ICMS NA ENTRADA DO ESTADO',
                    ],
                    'dados_novos' => [
                        'id_cigam' => '000001',
                        'nome' => 'CEARA',
                        'abreviacao' => 'CE',
                        'descricao' => 'NOVA DESCRICAO',
                    ],
                    'campos_alterados' => [[
                        'campo' => 'descricao',
                        'atual' => 'PAGA ICMS NA ENTRADA DO ESTADO',
                        'novo' => 'NOVA DESCRICAO',
                    ]],
                ]],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.estados.importar.confirmar', $importacao), [
                'row_ids_novas' => [1],
                'row_ids_atualizacoes' => [2],
            ])
            ->assertOk()
            ->assertJsonPath('resumo.criadas', 1)
            ->assertJsonPath('resumo.atualizadas', 1);

        $this->assertDatabaseHas('estados', [
            'id_cigam' => '000014',
            'nome' => 'MATO GROSSO',
            'abreviacao' => 'MT',
        ]);
        $this->assertSame('NOVA DESCRICAO', $estado->fresh()->descricao);
    }

    /**
     * @param  list<array{0:mixed, 1:mixed, 2:mixed, 3:mixed|null}>  $rows
     */
    private function storeSpreadsheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ID CIGAM', 'Nome', 'Abreviação', 'Descrição'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'estados-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = 'estados/importacoes/teste.xlsx';
        Storage::disk('local')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function importRow(int $rowId, string $idCigam, string $nome, string $sigla, ?string $descricao): array
    {
        return [
            'row_id' => $rowId,
            'linha' => $rowId + 1,
            'dados' => [
                'id_cigam' => $idCigam,
                'nome' => $nome,
                'abreviacao' => $sigla,
                'descricao' => $descricao,
            ],
        ];
    }
}
