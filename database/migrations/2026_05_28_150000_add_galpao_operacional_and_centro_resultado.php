<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (! Schema::hasColumn('unidades_negocio', 'is_galpao_operacional')) {
                $afterColumn = Schema::hasColumn('unidades_negocio', 'is_unidade_producao')
                    ? 'is_unidade_producao'
                    : 'is_hub';
                $table->boolean('is_galpao_operacional')->default(false)->after($afterColumn);
            }
        });

        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('movimentacoes', 'id_unidade_negocio_centro_resultado')) {
                $table->foreignId('id_unidade_negocio_centro_resultado')
                    ->nullable()
                    ->after('id_unidade_negocio_faturamento')
                    ->constrained('unidades_negocio', indexName: 'mov_un_centro_res_fk')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        });

        Schema::table('vendas_notas', function (Blueprint $table): void {
            if (! Schema::hasColumn('vendas_notas', 'id_unidade_negocio_centro_resultado')) {
                $table->foreignId('id_unidade_negocio_centro_resultado')
                    ->nullable()
                    ->after('id_unidade_negocio_faturamento')
                    ->constrained('unidades_negocio', indexName: 'venda_nota_centro_res_fk')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendas_notas', function (Blueprint $table): void {
            if (Schema::hasColumn('vendas_notas', 'id_unidade_negocio_centro_resultado')) {
                $table->dropForeign('venda_nota_centro_res_fk');
                $table->dropColumn('id_unidade_negocio_centro_resultado');
            }
        });

        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('movimentacoes', 'id_unidade_negocio_centro_resultado')) {
                $table->dropForeign('mov_un_centro_res_fk');
                $table->dropColumn('id_unidade_negocio_centro_resultado');
            }
        });

        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (Schema::hasColumn('unidades_negocio', 'is_galpao_operacional')) {
                $table->dropColumn('is_galpao_operacional');
            }
        });
    }
};
