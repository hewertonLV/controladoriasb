<?php

namespace Tests\Feature\Admin\Estoques;

use App\Enums\Permissions;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\UnidadeNegocio;
use App\Services\Estoques\EstoqueImportacaoProcessor;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class EstoqueImportacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_processor_deriva_posicao_a_partir_de_um_e_preco_total(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '100001',
            'possui_estoque' => true,
        ]);
        $fruta = Fruta::factory()->create([
            'id_cigam' => '200001',
            'kg_por_unidade_medicao' => 10,
        ]);

        $path = 'estoques/importacoes/teste-import.xlsx';
        Storage::disk('local')->makeDirectory('estoques/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['ID CIGAM Unidade', 'ID CIGAM Fruta', 'Qtd UM', 'Preço total'],
            [$unidade->id_cigam, $fruta->id_cigam, '5', '250,00'],
        ]);

        $importacao = \App\Models\EstoqueImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'teste.xlsx',
            'arquivo_path' => $path,
            'status' => \App\Models\EstoqueImportacao::STATUS_AGUARDANDO,
        ]);

        app(EstoqueImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertSame(\App\Models\EstoqueImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertCount(1, $importacao->resultado['novas'] ?? []);

        $dados = $importacao->resultado['novas'][0]['dados'];
        $this->assertSame('5.00', $dados['qtd_fruta_um']);
        $this->assertSame('250.00', $dados['valor_total']);
        $this->assertSame('50.00', $dados['qtd_fruta_kg']);
        $this->assertSame('5.00', $dados['preco_medio_kg']);
    }

    public function test_processor_aceita_quantidade_zero_com_preco_total_zero(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '100002',
            'possui_estoque' => true,
        ]);
        $fruta = Fruta::factory()->create([
            'id_cigam' => '200002',
            'kg_por_unidade_medicao' => 10,
        ]);

        $path = 'estoques/importacoes/teste-import-zero.xlsx';
        Storage::disk('local')->makeDirectory('estoques/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['ID CIGAM Unidade', 'ID CIGAM Fruta', 'Qtd UM', 'Preço total'],
            [$unidade->id_cigam, $fruta->id_cigam, '0', '0'],
        ]);

        $importacao = \App\Models\EstoqueImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'teste-zero.xlsx',
            'arquivo_path' => $path,
            'status' => \App\Models\EstoqueImportacao::STATUS_AGUARDANDO,
        ]);

        app(EstoqueImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertSame(0, $importacao->erros_count);
        $this->assertCount(1, $importacao->resultado['novas'] ?? []);

        $dados = $importacao->resultado['novas'][0]['dados'];
        $this->assertSame('0.00', $dados['qtd_fruta_um']);
        $this->assertSame('0.00', $dados['valor_total']);
        $this->assertSame('0.00', $dados['qtd_fruta_kg']);
        $this->assertSame('0.00', $dados['preco_medio_kg']);
    }

    public function test_processor_aceita_quantidade_negativa(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '100003',
            'possui_estoque' => true,
        ]);
        $fruta = Fruta::factory()->create([
            'id_cigam' => '200003',
            'kg_por_unidade_medicao' => 10,
        ]);

        $path = 'estoques/importacoes/teste-import-negativo.xlsx';
        Storage::disk('local')->makeDirectory('estoques/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['ID CIGAM Unidade', 'ID CIGAM Fruta', 'Qtd UM', 'Preço total'],
            [$unidade->id_cigam, $fruta->id_cigam, '-2', '-100,00'],
        ]);

        $importacao = \App\Models\EstoqueImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'teste-negativo.xlsx',
            'arquivo_path' => $path,
            'status' => \App\Models\EstoqueImportacao::STATUS_AGUARDANDO,
        ]);

        app(EstoqueImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertSame(0, $importacao->erros_count);

        $dados = $importacao->resultado['novas'][0]['dados'];
        $this->assertSame('-2.00', $dados['qtd_fruta_um']);
        $this->assertSame('-100.00', $dados['valor_total']);
        $this->assertSame('-20.00', $dados['qtd_fruta_kg']);
        $this->assertSame('5.00', $dados['preco_medio_kg']);
    }

    public function test_processor_inclui_custo_operacional_kg_no_preview(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '100010',
            'possui_estoque' => true,
        ]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '2.25',
            'status_position' => true,
        ]);
        $fruta = Fruta::factory()->create([
            'id_cigam' => '200010',
            'kg_por_unidade_medicao' => 10,
        ]);

        $path = 'estoques/importacoes/teste-co-preview.xlsx';
        Storage::disk('local')->makeDirectory('estoques/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['ID CIGAM Unidade', 'ID CIGAM Fruta', 'Qtd UM', 'Preço total'],
            [$unidade->id_cigam, $fruta->id_cigam, '5', '250,00'],
        ]);

        $importacao = \App\Models\EstoqueImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'teste-co.xlsx',
            'arquivo_path' => $path,
            'status' => \App\Models\EstoqueImportacao::STATUS_AGUARDANDO,
        ]);

        app(EstoqueImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $dados = $importacao->resultado['novas'][0]['dados'];
        $this->assertSame('2.25', $dados['custo_operacional_kg']);
    }

    public function test_resultado_enriquece_custo_operacional_kg_em_preview_antiga(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '100012',
            'possui_estoque' => true,
            'custo_operacional' => '1.75',
        ]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.75',
            'status_position' => true,
        ]);
        $fruta = Fruta::factory()->create([
            'id_cigam' => '200012',
            'kg_por_unidade_medicao' => 10,
        ]);

        $user = $this->userWithPermissions([Permissions::ESTOQUES_IMPORTAR]);

        $importacao = \App\Models\EstoqueImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'preview-antiga.xlsx',
            'arquivo_path' => 'estoques/importacoes/preview-antiga.xlsx',
            'status' => \App\Models\EstoqueImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [],
                'atualizacoes' => [[
                    'row_id' => 1,
                    'linha' => 2,
                    'id_unidade_negocio' => $unidade->id,
                    'id_fruta' => $fruta->id,
                    'chave' => '100012|200012',
                    'dados_novos' => [
                        'qtd_fruta_um' => '5.00',
                        'valor_total' => '250.00',
                        'qtd_fruta_kg' => '50.00',
                        'preco_medio_kg' => '5.00',
                    ],
                ]],
            ],
        ]);

        $response = $this->actingAs($user)->getJson(
            route('admin.estoques.importar.resultado', $importacao),
        );

        $response->assertOk();
        $response->assertJsonPath('atualizacoes.0.dados_novos.custo_operacional_kg', '1.75');
    }

    public function test_confirmar_com_custo_operacional_incrementa_preco_medio_kg(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '100011',
            'possui_estoque' => true,
        ]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.00',
            'status_position' => true,
        ]);
        $fruta = Fruta::factory()->create([
            'id_cigam' => '200011',
            'kg_por_unidade_medicao' => 10,
        ]);

        $user = $this->userWithPermissions([
            Permissions::ESTOQUES_IMPORTAR,
            Permissions::ESTOQUES_IMPORTAR_CONFIRMAR,
        ]);

        $importacao = \App\Models\EstoqueImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'confirmar-co.xlsx',
            'arquivo_path' => 'estoques/importacoes/confirmar-co.xlsx',
            'status' => \App\Models\EstoqueImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [[
                    'row_id' => 1,
                    'linha' => 2,
                    'id_unidade_negocio' => $unidade->id,
                    'id_fruta' => $fruta->id,
                    'chave' => '100011|200011',
                    'dados' => [
                        'qtd_fruta_um' => '5.00',
                        'valor_total' => '250.00',
                        'qtd_fruta_kg' => '50.00',
                        'preco_medio_kg' => '5.00',
                        'custo_operacional_kg' => '1.00',
                    ],
                ]],
                'atualizacoes' => [],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(
            route('admin.estoques.importar.confirmar', $importacao),
            [
                'row_ids_novas' => [1],
                'aplicar_custo_operacional_por_row' => ['1' => true],
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('resumo.aplicadas', 1);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame('6.00', $estoque->preco_medio_kg);
        $this->assertSame('300.00', $estoque->valor_total_acumulado);
    }

    public function test_confirmar_sem_custo_operacional_mantem_preco_planilha(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '100012',
            'possui_estoque' => true,
        ]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.00',
            'status_position' => true,
        ]);
        $fruta = Fruta::factory()->create([
            'id_cigam' => '200012',
            'kg_por_unidade_medicao' => 10,
        ]);

        $user = $this->userWithPermissions([
            Permissions::ESTOQUES_IMPORTAR,
            Permissions::ESTOQUES_IMPORTAR_CONFIRMAR,
        ]);

        $importacao = \App\Models\EstoqueImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'confirmar-sem-co.xlsx',
            'arquivo_path' => 'estoques/importacoes/confirmar-sem-co.xlsx',
            'status' => \App\Models\EstoqueImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [[
                    'row_id' => 1,
                    'linha' => 2,
                    'id_unidade_negocio' => $unidade->id,
                    'id_fruta' => $fruta->id,
                    'chave' => '100012|200012',
                    'dados' => [
                        'qtd_fruta_um' => '5.00',
                        'valor_total' => '250.00',
                        'qtd_fruta_kg' => '50.00',
                        'preco_medio_kg' => '5.00',
                        'custo_operacional_kg' => '1.00',
                    ],
                ]],
                'atualizacoes' => [],
                'sem_alteracoes' => [],
                'erros' => [],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(
            route('admin.estoques.importar.confirmar', $importacao),
            [
                'row_ids_novas' => [1],
                'aplicar_custo_operacional_por_row' => ['1' => false],
            ],
        );

        $response->assertOk();

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame('5.00', $estoque->preco_medio_kg);
    }

    /**
     * @param  list<list<mixed>>  $linhas
     */
    private function criarPlanilha(string $caminho, array $linhas): void
    {
        $sheet = new Spreadsheet();
        $active = $sheet->getActiveSheet();

        foreach ($linhas as $rowIndex => $linha) {
            foreach ($linha as $colIndex => $valor) {
                $active->setCellValue([$colIndex + 1, $rowIndex + 1], $valor);
            }
        }

        (new Xlsx($sheet))->save($caminho);
        $sheet->disconnectWorksheets();
    }
}
