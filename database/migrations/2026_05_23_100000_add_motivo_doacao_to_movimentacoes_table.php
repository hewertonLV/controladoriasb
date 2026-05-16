<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            $table->string('motivo_doacao', 255)->nullable()->after('observacao_recebimento');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            $table->dropColumn('motivo_doacao');
        });
    }
};
