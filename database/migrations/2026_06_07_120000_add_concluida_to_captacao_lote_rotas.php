<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('captacao_lote_rotas', 'concluida')) {
            return;
        }

        Schema::table('captacao_lote_rotas', function (Blueprint $table): void {
            $table->boolean('concluida')->default(false)->after('id_veiculo');
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lote_rotas', function (Blueprint $table): void {
            $table->dropColumn('concluida');
        });
    }
};
