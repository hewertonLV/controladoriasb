<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('movimentacoes', 'conversao_origem_id')) {
                $table->foreignId('conversao_origem_id')
                    ->nullable()
                    ->after('devolucao_origem_id')
                    ->constrained('movimentacoes')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('movimentacoes', 'id_fruta_destino_conversao')) {
                $table->foreignId('id_fruta_destino_conversao')
                    ->nullable()
                    ->after('conversao_origem_id')
                    ->constrained('frutas')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('movimentacoes', 'qtd_resultante_um')) {
                $table->decimal('qtd_resultante_um', 15, 2)->default(0)->after('id_fruta_destino_conversao');
                $table->decimal('qtd_resultante_kg', 15, 2)->default(0)->after('qtd_resultante_um');
                $table->decimal('qtd_perda_conversao_um', 15, 2)->default(0)->after('qtd_resultante_kg');
                $table->decimal('qtd_perda_conversao_kg', 15, 2)->default(0)->after('qtd_perda_conversao_um');
                $table->decimal('valor_perda_conversao', 15, 2)->default(0)->after('qtd_perda_conversao_kg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('movimentacoes', 'id_fruta_destino_conversao')) {
                $table->dropConstrainedForeignId('id_fruta_destino_conversao');
            }

            if (Schema::hasColumn('movimentacoes', 'conversao_origem_id')) {
                $table->dropConstrainedForeignId('conversao_origem_id');
            }

            foreach (['valor_perda_conversao', 'qtd_perda_conversao_kg', 'qtd_perda_conversao_um', 'qtd_resultante_kg', 'qtd_resultante_um'] as $column) {
                if (Schema::hasColumn('movimentacoes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
