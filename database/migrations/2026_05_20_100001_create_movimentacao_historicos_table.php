<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacao_historicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('movimentacao_cadeia_raiz_id')
                ->constrained('movimentacoes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('movimentacao_antes_id')
                ->constrained('movimentacoes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('movimentacao_depois_id')
                ->constrained('movimentacoes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('origem', 40)->default('versionamento');
            $table->string('acao', 40);
            $table->text('motivo')->nullable();

            $table->json('dados_antes')->nullable();
            $table->json('dados_depois')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Nome explícito: índice composto auto-gerado excede 64 caracteres no MySQL.
            $table->index(['movimentacao_cadeia_raiz_id', 'created_at'], 'mov_hist_cadeia_raiz_created_idx');
            $table->index('acao', 'mov_hist_acao_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacao_historicos');
    }
};
