<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('movimentacoes', 'venda_nota_id')) {
                $table->foreignId('venda_nota_id')
                    ->nullable()
                    ->after('categoria_descarte_id')
                    ->constrained('vendas_notas')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('movimentacoes', 'id_unidade_negocio_faturamento')) {
                $table->foreignId('id_unidade_negocio_faturamento')
                    ->nullable()
                    ->after('venda_nota_id')
                    ->constrained('unidades_negocio')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('movimentacoes', 'valor_custo_saida')) {
                $table->decimal('valor_custo_saida', 15, 2)->default(0)->after('valor_total_movimentacao');
            }

            if (! Schema::hasColumn('movimentacoes', 'resultado_movimentacao')) {
                $table->decimal('resultado_movimentacao', 15, 2)->default(0)->after('valor_custo_saida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('movimentacoes', 'id_unidade_negocio_faturamento')) {
                $table->dropConstrainedForeignId('id_unidade_negocio_faturamento');
            }

            if (Schema::hasColumn('movimentacoes', 'venda_nota_id')) {
                $table->dropConstrainedForeignId('venda_nota_id');
            }

            foreach (['resultado_movimentacao', 'valor_custo_saida'] as $column) {
                if (Schema::hasColumn('movimentacoes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
