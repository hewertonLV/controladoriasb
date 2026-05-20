<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('movimentacoes', 'qtd_resultante_um')) {
            Schema::table('movimentacoes', function (Blueprint $table): void {
                $table->decimal('qtd_resultante_um', 15, 2)->default(0)->after('id_fruta_destino_conversao');
            });
        }

        if (! Schema::hasColumn('movimentacoes', 'qtd_resultante_kg')) {
            Schema::table('movimentacoes', function (Blueprint $table): void {
                $table->decimal('qtd_resultante_kg', 15, 2)->default(0)->after('qtd_resultante_um');
            });
        }

        if (! Schema::hasColumn('movimentacoes', 'qtd_perda_conversao_um')) {
            Schema::table('movimentacoes', function (Blueprint $table): void {
                $table->decimal('qtd_perda_conversao_um', 15, 2)->default(0)->after('qtd_resultante_kg');
            });
        }

        if (! Schema::hasColumn('movimentacoes', 'qtd_perda_conversao_kg')) {
            Schema::table('movimentacoes', function (Blueprint $table): void {
                $table->decimal('qtd_perda_conversao_kg', 15, 2)->default(0)->after('qtd_perda_conversao_um');
            });
        }

        if (! Schema::hasColumn('movimentacoes', 'valor_perda_conversao')) {
            Schema::table('movimentacoes', function (Blueprint $table): void {
                $table->decimal('valor_perda_conversao', 15, 2)->default(0)->after('qtd_perda_conversao_kg');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'valor_perda_conversao',
            'qtd_perda_conversao_kg',
            'qtd_perda_conversao_um',
            'qtd_resultante_kg',
            'qtd_resultante_um',
        ] as $column) {
            if (Schema::hasColumn('movimentacoes', $column)) {
                Schema::table('movimentacoes', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
