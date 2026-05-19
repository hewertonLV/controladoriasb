<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fruta_icms', function (Blueprint $table) {
            $table->string('um_icms_externo', 10)->default('KG')->after('icms_externo');
            $table->decimal('icms_venda_importada', 15, 2)->default(0)->after('um_icms');
            $table->string('um_icms_venda_importada', 10)->default('KG')->after('icms_venda_importada');
            $table->decimal('icms_venda_nacional', 15, 2)->default(0)->after('um_icms_venda_importada');
            $table->string('um_icms_venda_nacional', 10)->default('KG')->after('icms_venda_nacional');
        });

        Schema::table('fruta_icms', function (Blueprint $table) {
            $table->renameColumn('um_icms', 'um_icms_nacional');
        });

        DB::table('fruta_icms')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $updates = [
                    'um_icms_externo' => $row->um_icms_nacional ?? 'KG',
                ];

                if ($row->operacao === 'SAIDA') {
                    $updates['icms_venda_nacional'] = $row->icms_venda ?? 0;
                    $updates['um_icms_venda_nacional'] = 'KG';
                    $updates['icms_venda_importada'] = 0;
                    $updates['um_icms_venda_importada'] = 'KG';
                }

                DB::table('fruta_icms')->where('id', $row->id)->update($updates);
            }
        });

        Schema::table('fruta_icms', function (Blueprint $table) {
            $table->dropColumn('icms_venda');
        });
    }

    public function down(): void
    {
        Schema::table('fruta_icms', function (Blueprint $table) {
            $table->decimal('icms_venda', 15, 2)->default(0)->after('um_icms_nacional');
        });

        DB::table('fruta_icms')->where('operacao', 'SAIDA')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('fruta_icms')->where('id', $row->id)->update([
                    'icms_venda' => $row->icms_venda_nacional ?? 0,
                ]);
            }
        });

        Schema::table('fruta_icms', function (Blueprint $table) {
            $table->dropColumn([
                'um_icms_externo',
                'icms_venda_importada',
                'um_icms_venda_importada',
                'icms_venda_nacional',
                'um_icms_venda_nacional',
            ]);
            $table->renameColumn('um_icms_nacional', 'um_icms');
        });
    }
};
