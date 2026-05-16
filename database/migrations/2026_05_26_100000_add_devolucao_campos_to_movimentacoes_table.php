<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('movimentacoes', 'movimentacao_venda_origem_id')) {
                $table->foreignId('movimentacao_venda_origem_id')
                    ->nullable()
                    ->after('id_unidade_negocio_faturamento')
                    ->constrained('movimentacoes')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('movimentacoes', 'tipo_devolucao')) {
                $table->string('tipo_devolucao', 40)->nullable()->after('movimentacao_venda_origem_id');
            }

            if (! Schema::hasColumn('movimentacoes', 'numero_nf_devolucao')) {
                $table->string('numero_nf_devolucao')->nullable()->after('tipo_devolucao');
            }

            if (! Schema::hasColumn('movimentacoes', 'motivo_devolucao')) {
                $table->text('motivo_devolucao')->nullable()->after('numero_nf_devolucao');
            }

            foreach ([
                'valor_devolucao_total',
                'valor_devolucao_um',
                'valor_devolucao_kg',
                'valor_custo_devolucao',
                'resultado_devolucao',
            ] as $column) {
                if (! Schema::hasColumn('movimentacoes', $column)) {
                    $table->decimal($column, 15, 2)->default(0)->after('motivo_devolucao');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            foreach ([
                'resultado_devolucao',
                'valor_custo_devolucao',
                'valor_devolucao_kg',
                'valor_devolucao_um',
                'valor_devolucao_total',
                'motivo_devolucao',
                'numero_nf_devolucao',
                'tipo_devolucao',
            ] as $column) {
                if (Schema::hasColumn('movimentacoes', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('movimentacoes', 'movimentacao_venda_origem_id')) {
                $table->dropConstrainedForeignId('movimentacao_venda_origem_id');
            }
        });
    }
};
