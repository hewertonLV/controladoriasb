<?php

use App\Enums\FrutaIcmsOperacao;
use App\Models\Estado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fruta_icms') || ! Schema::hasTable('frutas')) {
            return;
        }

        if (! Schema::hasColumn('frutas', 'icms_ex_compra')) {
            return;
        }

        $idCeara = Estado::ID_CEARA;
        $now = now();

        DB::table('frutas')->orderBy('id')->chunkById(200, function ($frutas) use ($idCeara, $now): void {
            foreach ($frutas as $fruta) {
                DB::table('fruta_icms')->updateOrInsert(
                    [
                        'fruta_id' => $fruta->id,
                        'id_estado' => $idCeara,
                        'operacao' => FrutaIcmsOperacao::ENTRADA->value,
                    ],
                    [
                        'icms_externo' => $fruta->icms_ex_compra ?? 0,
                        'icms_nacional' => $fruta->icms_na_compra ?? 0,
                        'um_icms' => $fruta->um_icms ?? 'KG',
                        'icms_venda' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );

                DB::table('fruta_icms')->updateOrInsert(
                    [
                        'fruta_id' => $fruta->id,
                        'id_estado' => $idCeara,
                        'operacao' => FrutaIcmsOperacao::SAIDA->value,
                    ],
                    [
                        'icms_externo' => 0,
                        'icms_nacional' => 0,
                        'um_icms' => 'KG',
                        'icms_venda' => $fruta->icms_venda ?? 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        });

        Schema::table('frutas', function (Blueprint $table) {
            $table->dropColumn([
                'icms_ex_compra',
                'icms_na_compra',
                'um_icms',
                'icms_venda',
            ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('frutas')) {
            return;
        }

        Schema::table('frutas', function (Blueprint $table) {
            if (! Schema::hasColumn('frutas', 'icms_ex_compra')) {
                $table->decimal('icms_ex_compra', 15, 2)->default(0)->after('kg_por_unidade_medicao');
                $table->decimal('icms_na_compra', 15, 2)->default(0)->after('icms_ex_compra');
                $table->string('um_icms', 255)->default('KG')->after('icms_na_compra');
                $table->decimal('icms_venda', 15, 2)->default(0)->after('um_icms');
            }
        });

        $idCeara = Estado::ID_CEARA;

        DB::table('fruta_icms')
            ->where('id_estado', $idCeara)
            ->orderBy('fruta_id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if ($row->operacao === FrutaIcmsOperacao::ENTRADA->value) {
                        DB::table('frutas')->where('id', $row->fruta_id)->update([
                            'icms_ex_compra' => $row->icms_externo,
                            'icms_na_compra' => $row->icms_nacional,
                            'um_icms' => $row->um_icms,
                        ]);
                    }
                    if ($row->operacao === FrutaIcmsOperacao::SAIDA->value) {
                        DB::table('frutas')->where('id', $row->fruta_id)->update([
                            'icms_venda' => $row->icms_venda,
                        ]);
                    }
                }
            }, 'id');
    }
};
