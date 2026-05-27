<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->dropUnique('cap_lote_data_cart_uq');
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->unique(
                ['data_referencia', 'id_captacao_carteira'],
                'cap_lote_data_cart_uq',
            );
        });
    }
};
