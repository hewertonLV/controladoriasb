<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frutas', function (Blueprint $table) {
            $table->id();
            $table->string('id_cigam', 6)->unique();
            $table->string('nome', 255);
            $table->string('unidade_medicao', 20);
            $table->decimal('kg_por_unidade_medicao', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frutas');
    }
};
