<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pedidos', 'id_unidade_negocio_saida_venda')) {
                $table->foreignId('id_unidade_negocio_saida_venda')
                    ->nullable()
                    ->after('id_cliente')
                    ->constrained('unidades_negocio', indexName: 'ped_saida_venda_un_fk')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (Schema::hasColumn('pedidos', 'id_unidade_negocio_saida_venda')) {
                $table->dropForeign('ped_saida_venda_un_fk');
                $table->dropColumn('id_unidade_negocio_saida_venda');
            }
        });
    }
};
