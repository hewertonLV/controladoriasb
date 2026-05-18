<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('movimentacoes', 'valor_total_movimentacao')) {
            return;
        }

        Schema::table('movimentacoes', function (Blueprint $table): void {
            $table->decimal('valor_total_movimentacao', 15, 2)->default(0)->after('valor_nf_kg');
        });

        // Retrocompatibilidade: doações antigas sem coluna preenchida (valor econômico estava em valor_nf_*).
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement(
                "UPDATE movimentacoes SET valor_total_movimentacao = ROUND(CAST(preco_medio_fruta_kg AS REAL) * CAST(qtd_fruta_kg AS REAL), 2) WHERE categoria_movimentacao_id = 4 AND (valor_total_movimentacao IS NULL OR valor_total_movimentacao = 0)",
            );
        } else {
            DB::statement(
                'UPDATE movimentacoes SET valor_total_movimentacao = ROUND(preco_medio_fruta_kg * qtd_fruta_kg, 2) WHERE categoria_movimentacao_id = 4 AND (valor_total_movimentacao IS NULL OR valor_total_movimentacao = 0)',
            );
        }

        DB::table('movimentacoes')
            ->where('categoria_movimentacao_id', 4)
            ->update([
                'valor_nf_total' => 0,
                'valor_nf_um' => 0,
                'valor_nf_kg' => 0,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('movimentacoes', 'valor_total_movimentacao')) {
            return;
        }

        Schema::table('movimentacoes', function (Blueprint $table): void {
            $table->dropColumn('valor_total_movimentacao');
        });
    }
};
