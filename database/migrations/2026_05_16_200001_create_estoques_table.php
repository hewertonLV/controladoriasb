<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_unidade_negocio')
                ->constrained('unidades_negocio')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_fruta')
                ->constrained('frutas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('qtd_fruta_kg', 15, 2)->default(0);
            $table->decimal('qtd_fruta_um', 15, 2)->default(0);
            $table->decimal('preco_medio_kg', 15, 2)->default(0);
            $table->decimal('preco_medio_um', 15, 2)->default(0);
            $table->decimal('valor_total_acumulado', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['id_unidade_negocio', 'id_fruta']);
            $table->index('id_unidade_negocio');
            $table->index('id_fruta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoques');
    }
};
