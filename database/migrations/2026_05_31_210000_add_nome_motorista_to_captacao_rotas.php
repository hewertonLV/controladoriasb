<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('captacao_rotas', 'nome_motorista')) {
            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->string('nome_motorista', 120)->nullable()->after('nome');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('captacao_rotas', 'nome_motorista')) {
            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->dropColumn('nome_motorista');
            });
        }
    }
};
