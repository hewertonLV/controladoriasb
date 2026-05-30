<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->foreignId('id_captacao_rota')
                ->nullable()
                ->after('id_captacao_lote')
                ->constrained('captacao_rotas', indexName: 'cap_lote_mov_rota_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('id_pedido')
                ->nullable()
                ->after('id_captacao_rota')
                ->constrained('pedidos', indexName: 'cap_lote_mov_ped_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedBigInteger('id_transferencia_origem_dependencia')
                ->nullable()
                ->after('transferencia_origem_id');
            $table->foreignId('id_unidade_negocio_origem')
                ->nullable()
                ->after('id_fruta')
                ->constrained('unidades_negocio', indexName: 'cap_lote_mov_un_orig_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(
                ['id_captacao_lote', 'id_captacao_rota', 'tipo', 'id_fruta', 'id_unidade_negocio_origem'],
                'cap_lote_mov_rota_dem_idx',
            );
        });

        Schema::table('vendas_notas', function (Blueprint $table): void {
            $table->string('status_conclusao', 32)->default('CONCLUIDA')->after('status_registro');
            $table->unsignedBigInteger('id_transferencia_origem_bloqueio')->nullable()->after('status_conclusao');
            $table->index('status_conclusao', 'vendas_notas_status_conclusao_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_notas', function (Blueprint $table): void {
            $table->dropIndex('vendas_notas_status_conclusao_idx');
            $table->dropColumn(['status_conclusao', 'id_transferencia_origem_bloqueio']);
        });

        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->dropIndex('cap_lote_mov_rota_dem_idx');
            $table->dropConstrainedForeignId('id_unidade_negocio_origem');
            $table->dropColumn('id_transferencia_origem_dependencia');
            $table->dropConstrainedForeignId('id_pedido');
            $table->dropConstrainedForeignId('id_captacao_rota');
        });
    }
};
