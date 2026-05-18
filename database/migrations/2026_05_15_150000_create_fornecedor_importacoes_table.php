<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedor_importacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('arquivo_original')->nullable();
            $table->string('arquivo_path');

            $table->string('status', 30)->index();

            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('linhas_processadas')->default(0);
            $table->unsignedTinyInteger('percentual')->default(0);

            $table->unsignedInteger('novas_count')->default(0);
            $table->unsignedInteger('atualizacoes_count')->default(0);
            $table->unsignedInteger('sem_alteracoes_count')->default(0);
            $table->unsignedInteger('erros_count')->default(0);

            $table->json('resultado')->nullable();
            $table->text('erro_mensagem')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedor_importacoes');
    }
};
