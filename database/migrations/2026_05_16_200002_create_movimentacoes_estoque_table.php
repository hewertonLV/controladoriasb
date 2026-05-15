<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_estoque')
                ->constrained('estoques')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
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
            $table->decimal('valor_total_fruta', 15, 2)->default(0);
            $table->boolean('status_ultima_posicao')->default(true)->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['id_estoque', 'status_ultima_posicao']);
            $table->index(['id_unidade_negocio', 'id_fruta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
