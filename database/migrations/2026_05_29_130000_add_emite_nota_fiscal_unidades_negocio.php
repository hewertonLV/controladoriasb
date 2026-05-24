<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (! Schema::hasColumn('unidades_negocio', 'emite_nota_fiscal')) {
                $table->boolean('emite_nota_fiscal')->default(true)->after('is_galpao_operacional');
            }
        });

        if (Schema::hasColumn('unidades_negocio', 'is_galpao_operacional')) {
            DB::table('unidades_negocio')
                ->where('is_galpao_operacional', true)
                ->update(['emite_nota_fiscal' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table): void {
            if (Schema::hasColumn('unidades_negocio', 'emite_nota_fiscal')) {
                $table->dropColumn('emite_nota_fiscal');
            }
        });
    }
};
