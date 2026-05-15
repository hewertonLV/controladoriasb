<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frutas', function (Blueprint $table) {
            $table->decimal('icms_ex_compra', 15, 2)->default(0)->after('kg_por_unidade_medicao');
            $table->decimal('icms_na_compra', 15, 2)->default(0)->after('icms_ex_compra');
            $table->string('um_icms', 255)->default('KG')->after('icms_na_compra');
            $table->decimal('icms_venda', 15, 2)->default(0)->after('um_icms');
        });
    }

    public function down(): void
    {
        Schema::table('frutas', function (Blueprint $table) {
            $table->dropColumn([
                'icms_ex_compra',
                'icms_na_compra',
                'um_icms',
                'icms_venda',
            ]);
        });
    }
};
