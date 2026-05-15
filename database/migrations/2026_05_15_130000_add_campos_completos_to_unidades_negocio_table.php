<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->string('razao_social', 255)->default('')->after('id_cigam');
            $table->string('cpf_cnpj', 14)->default('00000000000')->after('nome');
            $table->decimal('custo_operacional', 15, 2)->default(0)->after('cpf_cnpj');
        });

        foreach (DB::table('unidades_negocio')->select('id', 'id_cigam', 'nome')->get() as $row) {
            $digits = preg_replace('/\D/', '', (string) $row->id_cigam) ?? '';

            if ($digits !== '' && strlen($digits) > 6) {
                $digits = substr($digits, -6);
            }

            $idCigam = $digits === '' ? '000000' : str_pad($digits, 6, '0', STR_PAD_LEFT);
            $razaoSocial = mb_strtoupper(trim((string) $row->nome), 'UTF-8');

            DB::table('unidades_negocio')->where('id', $row->id)->update([
                'id_cigam' => $idCigam,
                'razao_social' => $razaoSocial !== '' ? $razaoSocial : 'SEM RAZAO SOCIAL',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->dropColumn(['razao_social', 'cpf_cnpj', 'custo_operacional']);
        });
    }
};
