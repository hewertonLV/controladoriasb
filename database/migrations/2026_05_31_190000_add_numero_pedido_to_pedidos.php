<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pedidos', 'numero_pedido')) {
                $table->string('numero_pedido', 60)->nullable()->after('id_captacao_rota');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (Schema::hasColumn('pedidos', 'numero_pedido')) {
                $table->dropColumn('numero_pedido');
            }
        });
    }
};
