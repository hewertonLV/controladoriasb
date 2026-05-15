<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $unidades = DB::table('unidades_negocio')->select('id', 'custo_operacional')->get();

        foreach ($unidades as $unidade) {
            DB::table('historico_c_o_un_ng')->insert([
                'id_unidade_negocio' => $unidade->id,
                'custo_operacional' => $unidade->custo_operacional ?? 0,
                'status_position' => true,
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('historico_c_o_un_ng')->truncate();
    }
};
