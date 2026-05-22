<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frutas', function (Blueprint $table) {
            $table->decimal('kg_por_unidade_medicao', 15, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('frutas', function (Blueprint $table) {
            $table->decimal('kg_por_unidade_medicao', 15, 2)->default(0)->change();
        });
    }
};
