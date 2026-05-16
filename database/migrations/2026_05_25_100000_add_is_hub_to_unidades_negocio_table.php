<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (! Schema::hasColumn('unidades_negocio', 'is_hub')) {
                $table->boolean('is_hub')->default(false)->after('possui_estoque');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (Schema::hasColumn('unidades_negocio', 'is_hub')) {
                $table->dropColumn('is_hub');
            }
        });
    }
};
