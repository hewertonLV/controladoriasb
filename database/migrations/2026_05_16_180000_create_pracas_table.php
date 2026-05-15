<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pracas', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255)->index();
            $table->unsignedBigInteger('id_unidade_negocio')->index();
            $table->timestamps();

            $table->unique(['nome', 'id_unidade_negocio']);

            $table->foreign('id_unidade_negocio')
                ->references('id')
                ->on('unidades_negocio')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pracas');
    }
};
