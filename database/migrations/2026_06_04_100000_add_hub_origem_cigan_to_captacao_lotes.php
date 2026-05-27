<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->foreignId('id_unidade_negocio_hub_origem')
                ->nullable()
                ->after('id_unidade_negocio_galpao')
                ->constrained('unidades_negocio', indexName: 'cap_lote_hub_orig_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->dropForeign('cap_lote_hub_orig_fk');
            $table->dropColumn('id_unidade_negocio_hub_origem');
        });
    }
};
