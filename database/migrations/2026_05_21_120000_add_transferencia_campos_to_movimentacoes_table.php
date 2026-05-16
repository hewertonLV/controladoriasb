<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->foreignId('status_movimentacao_id')
                ->nullable()
                ->after('categoria_movimentacao_id')
                ->constrained('status_movimentacoes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('status_transferencia', 40)->nullable()->after('status_movimentacao_id');
            $table->unsignedBigInteger('transferencia_origem_id')->nullable()->after('status_transferencia');
            $table->unsignedBigInteger('pareada_movimentacao_id')->nullable()->after('transferencia_origem_id');

            $table->string('numero_nf_origem', 120)->nullable()->after('pareada_movimentacao_id');
            $table->string('numero_nf_destino', 120)->nullable()->after('numero_nf_origem');

            $table->decimal('qtd_recebida_um', 15, 2)->nullable()->after('qtd_fruta_kg');
            $table->decimal('qtd_recebida_kg', 15, 2)->nullable()->after('qtd_recebida_um');

            $table->string('status_recebimento', 20)->nullable()->after('qtd_recebida_kg');

            $table->text('observacao')->nullable()->after('status_recebimento');
            $table->text('observacao_recebimento')->nullable()->after('observacao');
        });

        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->foreign('transferencia_origem_id')
                ->references('id')
                ->on('movimentacoes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('pareada_movimentacao_id')
                ->references('id')
                ->on('movimentacoes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Nomes explícitos: índices compostos auto-gerados excedem 64 caracteres no MySQL.
            $table->index(['categoria_movimentacao_id', 'status_transferencia'], 'mov_cat_status_transfer_idx');
            $table->index(['transferencia_origem_id', 'status_registro'], 'mov_transf_orig_stat_reg_idx');
            $table->index(['id_frete', 'categoria_movimentacao_id', 'status_registro'], 'mov_frete_cat_stat_reg_idx');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->dropForeign(['status_movimentacao_id']);
            $table->dropForeign(['transferencia_origem_id']);
            $table->dropForeign(['pareada_movimentacao_id']);
        });

        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->dropIndex('mov_cat_status_transfer_idx');
            $table->dropIndex('mov_transf_orig_stat_reg_idx');
            $table->dropIndex('mov_frete_cat_stat_reg_idx');
        });

        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->dropColumn([
                'status_movimentacao_id',
                'status_transferencia',
                'transferencia_origem_id',
                'pareada_movimentacao_id',
                'numero_nf_origem',
                'numero_nf_destino',
                'qtd_recebida_um',
                'qtd_recebida_kg',
                'status_recebimento',
                'observacao',
                'observacao_recebimento',
            ]);
        });
    }
};
