<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pedidos', 'ordem_carregamento')) {
                $table->unsignedSmallInteger('ordem_carregamento')->nullable()->after('numero_pedido');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (Schema::hasColumn('pedidos', 'ordem_carregamento')) {
                $table->dropColumn('ordem_carregamento');
            }
        });
    }
};
