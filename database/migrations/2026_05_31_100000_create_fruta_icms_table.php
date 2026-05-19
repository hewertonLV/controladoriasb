<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruta_icms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fruta_id')
                ->constrained('frutas')
                ->cascadeOnDelete();

            $table->foreignId('id_estado')
                ->constrained('estados')
                ->cascadeOnDelete();

            $table->string('operacao', 10);

            $table->decimal('icms_externo', 15, 2)->default(0);
            $table->decimal('icms_nacional', 15, 2)->default(0);
            $table->string('um_icms', 10)->default('KG');
            $table->decimal('icms_venda', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['fruta_id', 'id_estado', 'operacao'], 'fruta_icms_uk');
            $table->index(['id_estado', 'operacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fruta_icms');
    }
};
