<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->string('status_demanda', 20)->default('ABERTO')->after('tipo');
            $table->index(['id_captacao_lote', 'id_captacao_rota', 'status_demanda'], 'cap_lote_mov_rota_st_idx');
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->dropIndex('cap_lote_mov_rota_st_idx');
            $table->dropColumn('status_demanda');
        });
    }
};
