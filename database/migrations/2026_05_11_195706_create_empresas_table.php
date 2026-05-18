<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();

            $table->string('id_cigam', 50)->unique();
            $table->boolean('status')->default(true);
            $table->string('nome', 255);
            $table->string('fantasia', 255)->nullable();
            $table->string('cpf_cnpj', 14)->unique();
            $table->unsignedInteger('unidade_negocio');
            $table->string('tipo_pessoa', 10);

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('tipo_pessoa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
