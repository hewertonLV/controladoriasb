<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->string('arquivo_nf_transferencia_path', 255)->nullable()->after('id_unidade_negocio_hub_origem');
            $table->string('arquivo_nf_transferencia_nome', 255)->nullable()->after('arquivo_nf_transferencia_path');
            $table->timestamp('nf_transferencia_enviada_em')->nullable()->after('arquivo_nf_transferencia_nome');
            $table->foreignId('nf_transferencia_user_id')
                ->nullable()
                ->after('nf_transferencia_enviada_em')
                ->constrained('users', indexName: 'cap_lote_nf_user_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->dropForeign('cap_lote_nf_user_fk');
            $table->dropColumn([
                'arquivo_nf_transferencia_path',
                'arquivo_nf_transferencia_nome',
                'nf_transferencia_enviada_em',
                'nf_transferencia_user_id',
            ]);
        });
    }
};
