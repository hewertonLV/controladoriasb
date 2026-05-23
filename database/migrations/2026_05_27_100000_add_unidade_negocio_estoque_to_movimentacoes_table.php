<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('movimentacoes', 'id_unidade_negocio_estoque')) {
                $table->foreignId('id_unidade_negocio_estoque')
                    ->nullable()
                    ->after('id_unidade_negocio_faturamento')
                    ->constrained('unidades_negocio', indexName: 'mov_un_estoque_fk')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('movimentacoes', 'id_unidade_negocio_estoque')) {
                $table->dropForeign('mov_un_estoque_fk');
                $table->dropColumn('id_unidade_negocio_estoque');
            }
        });
    }
};
