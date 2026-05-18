<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unidade_negocio_user')) {
            return;
        }

        Schema::create('unidade_negocio_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('unidade_negocio_id')->constrained('unidades_negocio')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'unidade_negocio_id']);
            $table->index('unidade_negocio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade_negocio_user');
    }
};
