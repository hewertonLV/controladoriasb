<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->foreignId('id_cliente')
                ->nullable()
                ->after('id_estado')
                ->constrained('clientes')
                ->nullOnDelete();

            $table->unique('id_cliente', 'un_negocio_id_cliente_unique');
        });
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->dropUnique('un_negocio_id_cliente_unique');
            $table->dropConstrainedForeignId('id_cliente');
        });
    }
};
