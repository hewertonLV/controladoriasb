<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
