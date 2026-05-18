<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoques', function (Blueprint $table): void {
            $table->unsignedTinyInteger('ativo_unico')->nullable()->default(1)->after('deleted_at');
        });

        DB::table('estoques')
            ->whereNotNull('deleted_at')
            ->update(['ativo_unico' => null]);

        Schema::table('estoques', function (Blueprint $table): void {
            $table->dropUnique('estoques_id_unidade_negocio_id_fruta_unique');
            $table->unique(['id_unidade_negocio', 'id_fruta', 'ativo_unico'], 'estoques_unidade_fruta_ativo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('estoques', function (Blueprint $table): void {
            $table->dropUnique('estoques_unidade_fruta_ativo_unique');
            $table->dropColumn('ativo_unico');
            $table->unique(['id_unidade_negocio', 'id_fruta']);
        });
    }
};
