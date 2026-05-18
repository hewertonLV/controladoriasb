<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            $table->foreignId('cancelada_por')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamp('cancelada_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            $table->dropForeign(['cancelada_por']);
            $table->dropColumn(['cancelada_por', 'cancelada_em', 'motivo_cancelamento']);
        });
    }
};
