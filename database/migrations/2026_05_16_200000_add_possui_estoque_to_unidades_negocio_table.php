<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->boolean('possui_estoque')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->dropColumn('possui_estoque');
        });
    }
};
