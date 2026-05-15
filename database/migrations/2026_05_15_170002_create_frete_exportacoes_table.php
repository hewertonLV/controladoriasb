<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frete_exportacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('tipo', 20)->default('PDF');
            $table->string('status', 30)->index();
            $table->json('filtros')->nullable();

            $table->string('arquivo_path')->nullable();
            $table->string('arquivo_nome')->nullable();
            $table->unsignedInteger('total_registros')->nullable();

            $table->text('erro_mensagem')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['tipo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frete_exportacoes');
    }
};
