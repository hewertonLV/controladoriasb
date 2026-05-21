<?php

namespace Tests\Feature\Admin\Estoques;

use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Estoques\EstoqueImportacaoProcessor;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EstoqueImportacaoTest extends TestCase
{
    use RefreshDatabase;

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
