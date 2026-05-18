<?php

namespace Tests\Feature;

use App\Models\CategoriaDescarte;
use App\Models\CategoriaMovimentacao;
use App\Models\Cliente;
use App\Models\ClienteExportacao;
use App\Models\ClienteHistorico;
use App\Models\ClienteImportacao;
use App\Models\Empresa;
use App\Models\EmpresaExportacao;
use App\Models\EmpresaHistorico;
use App\Models\EmpresaImportacao;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\EstoqueExportacao;
use App\Models\EstoqueImportacao;
use App\Models\Fornecedor;
use App\Models\FornecedorExportacao;
use App\Models\FornecedorHistorico;
use App\Models\FornecedorImportacao;
use App\Models\Frete;
use App\Models\FreteExportacao;
use App\Models\FreteHistorico;
use App\Models\FreteImportacao;
use App\Models\Fruta;
use App\Models\FrutaExportacao;
use App\Models\FrutaHistorico;
use App\Models\FrutaImportacao;
use App\Models\Grupo;
use App\Models\GrupoExportacao;
use App\Models\GrupoHistorico;
use App\Models\GrupoImportacao;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\MovimentacaoHistorico;
use App\Models\Praca;
use App\Models\PracaExportacao;
use App\Models\PracaHistorico;
use App\Models\PracaImportacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioExportacao;
use App\Models\UnidadeNegocioHistorico;
use App\Models\UnidadeNegocioImportacao;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoExportacao;
use App\Models\VeiculoHistorico;
use App\Models\VeiculoImportacao;
use App\Models\VendaNota;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SoftDeletesApplicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function applicationTables(): array
    {
        return [
            'users',
            'permissions',
            'roles',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
            'empresas',
            'empresa_importacoes',
            'empresa_exportacoes',
            'empresa_historicos',
            'fornecedores',
            'fornecedor_importacoes',
            'fornecedor_exportacoes',
            'fornecedor_historicos',
            'clientes',
            'cliente_importacoes',
            'cliente_exportacoes',
            'cliente_historicos',
            'unidades_negocio',
            'unidade_negocio_importacoes',
            'unidade_negocio_exportacoes',
            'unidade_negocio_historicos',
            'historico_c_o_un_ng',
            'veiculos',
            'veiculo_importacoes',
            'veiculo_exportacoes',
            'veiculo_historicos',
            'fretes',
            'frete_importacoes',
            'frete_exportacoes',
            'frete_historicos',
            'frutas',
            'fruta_importacoes',
            'fruta_exportacoes',
            'fruta_historicos',
            'pracas',
            'praca_importacoes',
            'praca_exportacoes',
            'praca_historicos',
            'grupos',
            'grupo_importacoes',
            'grupo_exportacoes',
            'grupo_historicos',
            'estados',
            'status_movimentacoes',
            'categorias_movimentacao',
            'categorias_descarte',
            'estoques',
            'estoque_importacoes',
            'estoque_exportacoes',
            'movimentacoes',
            'movimentacoes_estoque',
            'movimentacao_historicos',
            'vendas_notas',
        ];
    }

    /**
     * @return list<class-string>
     */
    private function applicationModels(): array
    {
        return [
            CategoriaDescarte::class,
            CategoriaMovimentacao::class,
            Cliente::class,
            ClienteExportacao::class,
            ClienteHistorico::class,
            ClienteImportacao::class,
            Empresa::class,
            EmpresaExportacao::class,
            EmpresaHistorico::class,
            EmpresaImportacao::class,
            Estado::class,
            Estoque::class,
            EstoqueExportacao::class,
            EstoqueImportacao::class,
            Fornecedor::class,
            FornecedorExportacao::class,
            FornecedorHistorico::class,
            FornecedorImportacao::class,
            Frete::class,
            FreteExportacao::class,
            FreteHistorico::class,
            FreteImportacao::class,
            Fruta::class,
            FrutaExportacao::class,
            FrutaHistorico::class,
            FrutaImportacao::class,
            Grupo::class,
            GrupoExportacao::class,
            GrupoHistorico::class,
            GrupoImportacao::class,
            HistoricoCOUnNg::class,
            Movimentacao::class,
            MovimentacaoEstoque::class,
            MovimentacaoHistorico::class,
            Praca::class,
            PracaExportacao::class,
            PracaHistorico::class,
            PracaImportacao::class,
            StatusMovimentacao::class,
            UnidadeNegocio::class,
            UnidadeNegocioExportacao::class,
            UnidadeNegocioHistorico::class,
            UnidadeNegocioImportacao::class,
            User::class,
            Veiculo::class,
            VeiculoExportacao::class,
            VeiculoHistorico::class,
            VeiculoImportacao::class,
            VendaNota::class,
        ];
    }

    public function test_tabelas_da_aplicacao_possuem_deleted_at(): void
    {
        foreach ($this->applicationTables() as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'), "Tabela {$table} sem deleted_at.");
        }
    }

    public function test_models_da_aplicacao_usam_soft_deletes(): void
    {
        foreach ($this->applicationModels() as $modelClass) {
            $this->assertContains(SoftDeletes::class, class_uses_recursive($modelClass), "Model {$modelClass} sem SoftDeletes.");
        }
    }

    public function test_delete_de_model_aplica_soft_delete_e_mantem_registro_no_banco(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::query()->find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }
}
