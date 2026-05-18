<?php

use App\Enums\CategoriaMovimentacaoTipo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (! Schema::hasColumn('movimentacoes', 'numero_compra')) {
                $table->unsignedBigInteger('numero_compra')->nullable()->after('id');
                $table->index(['categoria_movimentacao_id', 'numero_compra'], 'movimentacoes_categoria_numero_compra_index');
            }
        });

        $this->backfillNumerosCompra();
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('movimentacoes', 'numero_compra')) {
                $table->dropIndex('movimentacoes_categoria_numero_compra_index');
                $table->dropColumn('numero_compra');
            }
        });
    }

    private function backfillNumerosCompra(): void
    {
        if (! Schema::hasColumn('movimentacoes', 'numero_compra')) {
            return;
        }

        $compras = DB::table('movimentacoes')
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->orderBy('data_movimentacao')
            ->orderBy('id')
            ->get(['id', 'movimentacao_origem_id']);

        $numerosPorRaiz = [];
        $proximoNumero = 1;

        foreach ($compras as $compra) {
            $raizId = (int) ($compra->movimentacao_origem_id ?? $compra->id);

            if (! isset($numerosPorRaiz[$raizId])) {
                $numerosPorRaiz[$raizId] = $proximoNumero++;
            }

            DB::table('movimentacoes')
                ->where('id', $compra->id)
                ->update(['numero_compra' => $numerosPorRaiz[$raizId]]);
        }
    }
};
