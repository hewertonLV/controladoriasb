<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->string('id_cigam', 6)->unique();

            $table->string('razao_social', 255);
            $table->string('cnpj_cpf', 14);

            $table->unsignedBigInteger('id_unidade_negocio');

            $table->decimal('desconto_nf', 15, 2)->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('id_unidade_negocio')
                ->references('id')
                ->on('unidades_negocio')
                ->cascadeOnDelete();

            $table->index('id_unidade_negocio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
