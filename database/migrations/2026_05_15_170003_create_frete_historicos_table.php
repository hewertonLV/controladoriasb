<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frete_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frete_id')
                ->constrained('fretes')
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

            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frete_historicos');
    }
};
