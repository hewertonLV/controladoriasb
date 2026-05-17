<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedor_importacoes', function (Blueprint $table): void {
            $table->index(['status', 'created_at', 'id'], 'fornecedor_importacoes_status_created_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('fornecedor_importacoes', function (Blueprint $table): void {
            $table->dropIndex('fornecedor_importacoes_status_created_id_index');
        });
    }
};
