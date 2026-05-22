<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\Permissions;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\MovimentacaoEstoque;
use App\Models\TransferenciaImportacao;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\TransferenciaImportacaoProcessor;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class TransferenciaImportacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
            PermissionSeeder::class,
        ]);
    }

    public function test_processor_classifica_linha_valida_como_nova(): void
    {
        [$origem, $destino, $fruta] = $this->criarCenarioComEstoqueOrigem();

        $path = 'transferencias/importacoes/teste.xlsx';
        Storage::disk('local')->makeDirectory('transferencias/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['CNPJ Origem', 'CNPJ Destino', 'ID CIGAM Fruta', 'Qtd UM', 'Número NF'],
            [$origem->cpf_cnpj, $destino->cpf_cnpj, $fruta->id_cigam, '2', 'NF-TRANS-001'],
        ]);

        $importacao = TransferenciaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'teste.xlsx',
            'arquivo_path' => $path,
            'status' => TransferenciaImportacao::STATUS_AGUARDANDO,
        ]);

        app(TransferenciaImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertSame(TransferenciaImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertCount(1, $importacao->resultado['novas'] ?? []);
        $this->assertSame('NF-TRANS-001', $importacao->resultado['novas'][0]['dados']['numero_nf_origem']);
    }

    public function test_confirmar_cria_transferencia_via_http(): void
    {
        [$origem, $destino, $fruta, $empresaOrigem, $empresaDestino] = $this->criarCenarioComEstoqueOrigem();

        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_IMPORTAR,
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_IMPORTAR_CONFIRMAR,
        ]);

        $path = 'transferencias/importacoes/http.xlsx';
        Storage::disk('local')->makeDirectory('transferencias/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['CNPJ Origem', 'CNPJ Destino', 'ID CIGAM Fruta', 'Qtd UM', 'Número NF'],
            [$origem->cpf_cnpj, $destino->cpf_cnpj, $fruta->id_cigam, '1', 'NF-99'],
        ]);

        $importacao = TransferenciaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'http.xlsx',
            'arquivo_path' => $path,
            'status' => TransferenciaImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [[
                    'row_id' => 1,
                    'linha' => 2,
                    'chave' => 'x',
                    'dados' => [
                        'id_empresa_origem' => $empresaOrigem->id,
                        'id_empresa_destino' => $empresaDestino->id,
                        'id_fruta' => $fruta->id,
                        'qtd_fruta_um' => '1.00',
                        'numero_nf_origem' => 'NF-99',
                    ],
                ]],
                'erros' => [],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(
            route('admin.movimentacoes.transferencias.importar.confirmar', $importacao),
            ['row_ids_novas' => [1]],
        );

        $response->assertOk();
        $response->assertJsonPath('resumo.aplicadas', 1);

        $this->assertDatabaseHas('movimentacoes', [
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'numero_nf_origem' => 'NF-99',
        ]);
    }

    public function test_mesma_origem_destino_fruta_com_nf_diferente_nao_e_duplicada(): void
    {
        [$origem, $destino, $fruta] = $this->criarCenarioComEstoqueOrigem();

        $path = 'transferencias/importacoes/nf-diferente.xlsx';
        Storage::disk('local')->makeDirectory('transferencias/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['CNPJ Origem', 'CNPJ Destino', 'ID CIGAM Fruta', 'Qtd UM', 'Número NF'],
            [$origem->cpf_cnpj, $destino->cpf_cnpj, $fruta->id_cigam, '5', '2153'],
            [$origem->cpf_cnpj, $destino->cpf_cnpj, $fruta->id_cigam, '5', '2164'],
        ]);

        $importacao = TransferenciaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'nf-diferente.xlsx',
            'arquivo_path' => $path,
            'status' => TransferenciaImportacao::STATUS_AGUARDANDO,
        ]);

        app(TransferenciaImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertSame(0, $importacao->erros_count);
        $this->assertCount(2, $importacao->resultado['novas'] ?? []);
    }

    public function test_linha_identica_em_todos_campos_e_duplicada(): void
    {
        [$origem, $destino, $fruta] = $this->criarCenarioComEstoqueOrigem();

        $path = 'transferencias/importacoes/duplicada.xlsx';
        Storage::disk('local')->makeDirectory('transferencias/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['CNPJ Origem', 'CNPJ Destino', 'ID CIGAM Fruta', 'Qtd UM', 'Número NF'],
            [$origem->cpf_cnpj, $destino->cpf_cnpj, $fruta->id_cigam, '3', '2164'],
            [$origem->cpf_cnpj, $destino->cpf_cnpj, $fruta->id_cigam, '3', '2164'],
        ]);

        $importacao = TransferenciaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'duplicada.xlsx',
            'arquivo_path' => $path,
            'status' => TransferenciaImportacao::STATUS_AGUARDANDO,
        ]);

        app(TransferenciaImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertCount(1, $importacao->resultado['novas'] ?? []);
        $this->assertCount(1, $importacao->resultado['erros'] ?? []);
        $this->assertStringContainsString(
            'Linha duplicada na planilha',
            $importacao->resultado['erros'][0]['erros'][0],
        );
    }

    public function test_tela_importar_exige_permissao(): void
    {
        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_TRANSFERENCIAS_VISUALIZAR,
        ]);

        $this->actingAs($user)
            ->get(route('admin.movimentacoes.transferencias.importar'))
            ->assertForbidden();
    }

    /**
     * @return array{0: UnidadeNegocio, 1: UnidadeNegocio, 2: Fruta, 3: Empresa, 4: Empresa}
     */
    private function criarCenarioComEstoqueOrigem(): array
    {
        $origem = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'cpf_cnpj' => '11222333000181',
        ]);
        $empresaOrigem = $origem->registroCorporativo()->firstOrFail();

        $destino = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'cpf_cnpj' => '11444777000161',
        ]);
        $empresaDestino = $destino->registroCorporativo()->firstOrFail();

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $destino->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);

        $fruta = Fruta::factory()->create([
            'id_cigam' => '300001',
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $estoque = Estoque::factory()->create([
            'id_unidade_negocio' => $origem->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $origem->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        return [$origem, $destino, $fruta, $empresaOrigem, $empresaDestino];
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
