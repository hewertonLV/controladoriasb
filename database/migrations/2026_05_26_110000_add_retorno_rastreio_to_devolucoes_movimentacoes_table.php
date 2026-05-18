<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('movimentacoes', 'id_unidade_negocio_retorno')) {
                $table->foreignId('id_unidade_negocio_retorno')
                    ->nullable()
                    ->after('id_unidade_negocio_faturamento')
                    ->constrained('unidades_negocio')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('movimentacoes', 'devolucao_origem_id')) {
                $table->foreignId('devolucao_origem_id')
                    ->nullable()
                    ->after('movimentacao_venda_origem_id')
                    ->constrained('movimentacoes')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('movimentacoes', 'devolucao_origem_id')) {
                $table->dropConstrainedForeignId('devolucao_origem_id');
            }

            if (Schema::hasColumn('movimentacoes', 'id_unidade_negocio_retorno')) {
                $table->dropConstrainedForeignId('id_unidade_negocio_retorno');
            }
        });
    }
};
