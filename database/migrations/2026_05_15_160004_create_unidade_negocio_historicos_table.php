<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidade_negocio_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_negocio_id')
                ->constrained('unidades_negocio')
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade_negocio_historicos');
    }
};
