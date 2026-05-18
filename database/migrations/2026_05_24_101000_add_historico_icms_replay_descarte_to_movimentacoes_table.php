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
            if (! Schema::hasColumn('movimentacoes', 'valor_icms_total')) {
                $table->decimal('valor_icms_total', 15, 2)->default(0)->after('icms_convertido_kg');
            }

            if (! Schema::hasColumn('movimentacoes', 'valor_icms_kg')) {
                $table->decimal('valor_icms_kg', 15, 2)->default(0)->after('valor_icms_total');
            }

            if (! Schema::hasColumn('movimentacoes', 'valor_icms_um')) {
                $table->decimal('valor_icms_um', 15, 2)->default(0)->after('valor_icms_kg');
            }

            if (! Schema::hasColumn('movimentacoes', 'versao_replay')) {
                $table->unsignedBigInteger('versao_replay')->default(1)->after('versao');
            }

            if (! Schema::hasColumn('movimentacoes', 'categoria_descarte_id')) {
                $table->foreignId('categoria_descarte_id')
                    ->nullable()
                    ->after('categoria_movimentacao_id')
                    ->constrained('categorias_descarte')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('movimentacoes', 'motivo_descarte')) {
                $table->text('motivo_descarte')->nullable()->after('motivo_doacao');
            }
        });

        $this->backfillIcmsHistorico();
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table): void {
            if (Schema::hasColumn('movimentacoes', 'categoria_descarte_id')) {
                $table->dropConstrainedForeignId('categoria_descarte_id');
            }

            foreach ([
                'motivo_descarte',
                'versao_replay',
                'valor_icms_um',
                'valor_icms_kg',
                'valor_icms_total',
            ] as $column) {
                if (Schema::hasColumn('movimentacoes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillIcmsHistorico(): void
    {
        if (! Schema::hasColumn('movimentacoes', 'icms_convertido_kg')) {
            return;
        }

        $query = DB::table('movimentacoes')
            ->where('icms_convertido_kg', '>', 0);

        foreach ($query->cursor() as $row) {
            $qtdKg = (float) ($row->qtd_fruta_kg ?? 0);
            $qtdUm = (float) ($row->qtd_fruta_um ?? 0);
            $icmsKg = (float) ($row->icms_convertido_kg ?? 0);
            $total = round($icmsKg * $qtdKg, 2);

            DB::table('movimentacoes')
                ->where('id', $row->id)
                ->update([
                    'valor_icms_kg' => $icmsKg,
                    'valor_icms_um' => $qtdUm > 0 ? round($total / $qtdUm, 2) : 0,
                    'valor_icms_total' => $total,
                ]);
        }

        DB::table('movimentacoes')
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
            ->update([
                'valor_icms_total' => 0,
                'valor_icms_kg' => 0,
                'valor_icms_um' => 0,
                'icms_convertido_kg' => 0,
            ]);
    }
};
