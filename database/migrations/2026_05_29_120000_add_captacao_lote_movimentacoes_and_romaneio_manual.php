<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captacao_romaneio_manual_linhas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_captacao_lote')
                ->constrained('captacao_lotes', indexName: 'cap_man_lin_lote_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_fruta')
                ->constrained('frutas', indexName: 'cap_man_lin_fruta_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('quantidade', 15, 3);
            $table->foreignId('id_unidade_origem_fisica')
                ->constrained('unidades_negocio', indexName: 'cap_man_lin_un_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_captacao_lote')
                ->constrained('captacao_lotes', indexName: 'cap_lote_mov_lote_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->foreignId('id_fruta')
                ->nullable()
                ->constrained('frutas', indexName: 'cap_lote_mov_fruta_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedBigInteger('transferencia_origem_id')->nullable();
            $table->foreignId('venda_nota_id')
                ->nullable()
                ->constrained('vendas_notas', indexName: 'cap_lote_mov_venda_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['id_captacao_lote', 'tipo'], 'cap_lote_mov_lote_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captacao_lote_movimentacoes');
        Schema::dropIfExists('captacao_romaneio_manual_linhas');
    }
};
