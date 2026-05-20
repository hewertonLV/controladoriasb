<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
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

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'deleted_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'deleted_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
