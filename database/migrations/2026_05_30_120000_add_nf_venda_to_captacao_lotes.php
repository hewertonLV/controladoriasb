<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->string('arquivo_nf_venda_path', 255)->nullable()->after('nf_transferencia_user_id');
            $table->string('arquivo_nf_venda_nome', 255)->nullable()->after('arquivo_nf_venda_path');
            $table->timestamp('nf_venda_enviada_em')->nullable()->after('arquivo_nf_venda_nome');
            $table->foreignId('nf_venda_user_id')
                ->nullable()
                ->after('nf_venda_enviada_em')
                ->constrained('users', indexName: 'cap_lote_nf_venda_user_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->dropForeign('cap_lote_nf_venda_user_fk');
            $table->dropColumn([
                'arquivo_nf_venda_path',
                'arquivo_nf_venda_nome',
                'nf_venda_enviada_em',
                'nf_venda_user_id',
            ]);
        });
    }
};
