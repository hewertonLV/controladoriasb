<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->char('centro_armazenagem', 3)->default('001')->after('id_cigam');
        });
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->dropColumn('centro_armazenagem');
        });
    }
};
