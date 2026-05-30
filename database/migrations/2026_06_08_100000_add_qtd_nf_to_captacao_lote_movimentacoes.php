<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->decimal('qtd_um', 15, 3)->nullable()->after('id_fruta');
            $table->string('nf_transferencia_path')->nullable()->after('transferencia_origem_id');
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->dropColumn(['qtd_um', 'nf_transferencia_path']);
        });
    }
};
