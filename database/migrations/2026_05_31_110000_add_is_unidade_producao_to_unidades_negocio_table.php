<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (! Schema::hasColumn('unidades_negocio', 'is_unidade_producao')) {
                $table->boolean('is_unidade_producao')->default(false)->after('is_hub');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (Schema::hasColumn('unidades_negocio', 'is_unidade_producao')) {
                $table->dropColumn('is_unidade_producao');
            }
        });
    }
};
