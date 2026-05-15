<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('id_sbs')->index();

            $table->string('nome', 255);
            $table->string('tipo', 255);
            $table->unsignedBigInteger('id_unidade_negocio');

            $table->string('status', 10)->index();

            $table->timestamps();

            $table->foreign('id_unidade_negocio')
                ->references('id')
                ->on('unidades_negocio')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};
