<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_movimentacao_estoque_old')
                ->nullable()
                ->constrained('movimentacoes_estoque')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_movimentacao_estoque_new')
                ->nullable()
                ->constrained('movimentacoes_estoque')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_empresa_origem')
                ->nullable()
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_empresa_destino')
                ->nullable()
                ->constrained('empresas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_fruta')
                ->constrained('frutas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('valor_nf_total', 15, 2)->default(0);
            $table->decimal('valor_nf_cx', 15, 2)->default(0);
            $table->decimal('valor_nf_kg', 15, 2)->default(0);

            $table->decimal('qtd_fruta_um', 15, 2)->default(0);
            $table->decimal('qtd_fruta_kg', 15, 2)->default(0);

            $table->foreignId('id_frete')
                ->nullable()
                ->constrained('fretes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->decimal('valor_frete_rateio', 15, 2)->default(0);
            $table->decimal('valor_frete_cx', 15, 2)->default(0);
            $table->decimal('valor_frete_kg', 15, 2)->default(0);

            $table->foreignId('id_custo_operacional')
                ->nullable()
                ->constrained('historico_c_o_un_ng')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('valor_custo_operacional', 15, 2)->default(0);

            $table->decimal('saldo_estoque_fruta_kg', 15, 2)->default(0);
            $table->decimal('saldo_estoque_fruta_um', 15, 2)->default(0);

            $table->decimal('preco_medio_fruta_kg', 15, 2)->default(0);
            $table->decimal('preco_medio_fruta_um', 15, 2)->default(0);

            $table->foreignId('categoria_movimentacao_id')
                ->constrained('categorias_movimentacao')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['categoria_movimentacao_id', 'id_fruta']);
            $table->index(['id_fruta', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes');
    }
};
