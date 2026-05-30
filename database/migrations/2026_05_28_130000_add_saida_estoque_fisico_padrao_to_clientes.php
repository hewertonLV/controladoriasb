<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clientes', 'saida_estoque_fisico_padrao')) {
            return;
        }

        $afterColumn = Schema::hasColumn('clientes', 'percentual_margem_alvo')
            ? 'percentual_margem_alvo'
            : 'desconto_nf';

        Schema::table('clientes', function (Blueprint $table) use ($afterColumn): void {
            $table->string('saida_estoque_fisico_padrao', 10)
                ->default('galpao')
                ->after($afterColumn);
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropColumn('saida_estoque_fisico_padrao');
        });
    }
};
