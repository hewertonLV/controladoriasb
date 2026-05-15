<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('praca_historicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('praca_id')
                ->constrained('pracas')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('origem', 30);
            $table->string('acao', 30);

            $table->json('dados_antes')->nullable();
            $table->json('dados_depois')->nullable();
            $table->json('alteracoes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['praca_id', 'created_at']);
            $table->index('acao');
            $table->index('origem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('praca_historicos');
    }
};
