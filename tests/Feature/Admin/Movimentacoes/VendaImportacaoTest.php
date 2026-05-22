<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\FrutaUmIcms;
use App\Enums\Permissions;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\MovimentacaoEstoque;
use App\Models\UnidadeNegocio;
use App\Models\VendaImportacao;
use App\Services\Movimentacoes\VendaImportacaoProcessor;
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

class VendaImportacaoTest extends TestCase
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
        [$unidade, $cliente, $fruta] = $this->criarCenarioComEstoqueOrigem();

        $path = 'vendas/importacoes/teste.xlsx';
        Storage::disk('local')->makeDirectory('vendas/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['Número NF', 'CNPJ Origem', 'CPF/CNPJ Cliente', 'ID CIGAM', 'Quantidade', 'UM', 'Valor Total'],
            ['NF-V-001', $unidade->cpf_cnpj, $cliente->cnpj_cpf, $fruta->id_cigam, '2', 'CAIXA', '300,00'],
        ]);

        $importacao = VendaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'teste.xlsx',
            'arquivo_path' => $path,
            'status' => VendaImportacao::STATUS_AGUARDANDO,
        ]);

        app(VendaImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertSame(VendaImportacao::STATUS_CONCLUIDO, $importacao->status);
        $this->assertCount(1, $importacao->resultado['novas'] ?? []);
        $this->assertSame('300.00', $importacao->resultado['novas'][0]['dados']['valor_nf_total']);
    }

    public function test_mesma_nf_fruta_com_valor_diferente_nao_e_duplicada(): void
    {
        [$unidade, $cliente, $fruta] = $this->criarCenarioComEstoqueOrigem();

        $path = 'vendas/importacoes/valor-diferente.xlsx';
        Storage::disk('local')->makeDirectory('vendas/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['Número NF', 'CNPJ Origem', 'CPF/CNPJ Cliente', 'ID CIGAM', 'Quantidade', 'UM', 'Valor Total'],
            ['NF-10', $unidade->cpf_cnpj, $cliente->cnpj_cpf, $fruta->id_cigam, '1', 'CAIXA', '100,00'],
            ['NF-10', $unidade->cpf_cnpj, $cliente->cnpj_cpf, $fruta->id_cigam, '1', 'CAIXA', '120,00'],
        ]);

        $importacao = VendaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'valor-diferente.xlsx',
            'arquivo_path' => $path,
            'status' => VendaImportacao::STATUS_AGUARDANDO,
        ]);

        app(VendaImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertCount(2, $importacao->resultado['novas'] ?? []);
        $this->assertCount(0, $importacao->resultado['erros'] ?? []);
    }

    public function test_linha_identica_em_todos_campos_e_duplicada(): void
    {
        [$unidade, $cliente, $fruta] = $this->criarCenarioComEstoqueOrigem();

        $path = 'vendas/importacoes/duplicada.xlsx';
        Storage::disk('local')->makeDirectory('vendas/importacoes');
        $this->criarPlanilha(Storage::disk('local')->path($path), [
            ['Número NF', 'CNPJ Origem', 'CPF/CNPJ Cliente', 'ID CIGAM', 'Quantidade', 'UM', 'Valor Total'],
            ['NF-20', $unidade->cpf_cnpj, $cliente->cnpj_cpf, $fruta->id_cigam, '3', 'CAIXA', '90,00'],
            ['NF-20', $unidade->cpf_cnpj, $cliente->cnpj_cpf, $fruta->id_cigam, '3', 'CAIXA', '90,00'],
        ]);

        $importacao = VendaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => null,
            'arquivo_original' => 'duplicada.xlsx',
            'arquivo_path' => $path,
            'status' => VendaImportacao::STATUS_AGUARDANDO,
        ]);

        app(VendaImportacaoProcessor::class)->processar($importacao->fresh());

        $importacao->refresh();
        $this->assertCount(1, $importacao->resultado['novas'] ?? []);
        $this->assertCount(1, $importacao->resultado['erros'] ?? []);
        $this->assertStringContainsString(
            'Linha duplicada na planilha',
            $importacao->resultado['erros'][0]['erros'][0],
        );
    }

    public function test_confirmar_cria_venda_via_http(): void
    {
        [$unidade, $cliente, $fruta, $empresaOrigem, $empresaCliente] = $this->criarCenarioComEstoqueOrigem();

        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_VENDAS_IMPORTAR,
            Permissions::MOVIMENTACOES_VENDAS_IMPORTAR_CONFIRMAR,
            Permissions::MOVIMENTACOES_VENDAS_CRIAR,
        ]);

        $importacao = VendaImportacao::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'arquivo_original' => 'http.xlsx',
            'arquivo_path' => 'vendas/importacoes/http.xlsx',
            'status' => VendaImportacao::STATUS_CONCLUIDO,
            'resultado' => [
                'novas' => [[
                    'row_id' => 1,
                    'linha' => 2,
                    'chave' => 'x',
                    'dados' => [
                        'numero_nf' => 'NF-IMPORT-99',
                        'id_empresa_origem' => $empresaOrigem->id,
                        'id_empresa_destino' => $empresaCliente->id,
                        'id_fruta' => $fruta->id,
                        'qtd_fruta_um' => '1.00',
                        'valor_nf_total' => '150.00',
                    ],
                ]],
                'erros' => [],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(
            route('admin.movimentacoes.vendas.importar.confirmar', $importacao),
            ['row_ids_novas' => [1]],
        );

        $response->assertOk();
        $response->assertJsonPath('resumo.aplicadas', 1);

        $this->assertDatabaseHas('vendas_notas', [
            'numero_nf' => 'NF-IMPORT-99',
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaCliente->id,
        ]);
    }

    public function test_tela_importar_exige_permissao(): void
    {
        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_VENDAS_VISUALIZAR,
        ]);

        $this->actingAs($user)
            ->get(route('admin.movimentacoes.vendas.importar'))
            ->assertForbidden();
    }

    /**
     * @return array{0: UnidadeNegocio, 1: Cliente, 2: Fruta, 3: Empresa, 4: Empresa}
     */
    private function criarCenarioComEstoqueOrigem(): array
    {
        $cliente = Cliente::factory()->create([
            'cnpj_cpf' => '12345678901',
        ]);
        $empresaCliente = $cliente->registroCorporativo()->firstOrFail();

        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'cpf_cnpj' => '11222333000181',
        ]);
        $empresaOrigem = $unidade->registroCorporativo()->firstOrFail();

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);

        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => 0,
            'entrada_externo' => 0,
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create([
            'id_cigam' => '400001',
            'unidade_medicao' => 'CAIXA',
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $estoque = Estoque::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_fruta' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        return [$unidade, $cliente, $fruta, $empresaOrigem, $empresaCliente];
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
