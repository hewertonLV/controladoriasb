<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_captacao_lote_movimentacao')
                ->constrained('captacao_lote_movimentacoes', indexName: 'cap_lote_mov_lin_mov_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_fruta')
                ->constrained('frutas', indexName: 'cap_lote_mov_lin_fruta_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('qtd_um', 15, 3);
            $table->timestamps();

            $table->unique(
                ['id_captacao_lote_movimentacao', 'id_fruta'],
                'cap_lote_mov_lin_mov_fruta_uq',
            );
        });

        $transferencias = DB::table('captacao_lote_movimentacoes')
            ->where('tipo', 'TRANSFERENCIA')
            ->whereNotNull('id_fruta')
            ->orderBy('id')
            ->get();

        foreach ($transferencias as $row) {
            DB::table('captacao_lote_movimentacao_linhas')->insert([
                'id_captacao_lote_movimentacao' => $row->id,
                'id_fruta' => $row->id_fruta,
                'qtd_um' => $row->qtd_um ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $grupos = DB::table('captacao_lote_movimentacoes')
            ->select([
                'id_captacao_lote',
                'id_captacao_rota',
                'id_unidade_negocio_origem',
                DB::raw('MIN(id) as manter_id'),
                DB::raw('COUNT(*) as total'),
            ])
            ->where('tipo', 'TRANSFERENCIA')
            ->whereNotNull('id_captacao_rota')
            ->whereNotNull('id_unidade_negocio_origem')
            ->groupBy('id_captacao_lote', 'id_captacao_rota', 'id_unidade_negocio_origem')
            ->having('total', '>', 1)
            ->get();

        foreach ($grupos as $grupo) {
            $manterId = (int) $grupo->manter_id;

            $duplicatas = DB::table('captacao_lote_movimentacoes')
                ->where('tipo', 'TRANSFERENCIA')
                ->where('id_captacao_lote', $grupo->id_captacao_lote)
                ->where('id_captacao_rota', $grupo->id_captacao_rota)
                ->where('id_unidade_negocio_origem', $grupo->id_unidade_negocio_origem)
                ->where('id', '!=', $manterId)
                ->orderBy('id')
                ->get();

            $manter = DB::table('captacao_lote_movimentacoes')->where('id', $manterId)->first();

            foreach ($duplicatas as $dup) {
                $linhasDup = DB::table('captacao_lote_movimentacao_linhas')
                    ->where('id_captacao_lote_movimentacao', $dup->id)
                    ->get();

                foreach ($linhasDup as $linhaDup) {
                    $existente = DB::table('captacao_lote_movimentacao_linhas')
                        ->where('id_captacao_lote_movimentacao', $manterId)
                        ->where('id_fruta', $linhaDup->id_fruta)
                        ->first();

                    if ($existente !== null) {
                        DB::table('captacao_lote_movimentacao_linhas')
                            ->where('id', $existente->id)
                            ->update([
                                'qtd_um' => round((float) $existente->qtd_um + (float) $linhaDup->qtd_um, 3),
                                'updated_at' => now(),
                            ]);
                        DB::table('captacao_lote_movimentacao_linhas')->where('id', $linhaDup->id)->delete();
                    } else {
                        DB::table('captacao_lote_movimentacao_linhas')
                            ->where('id', $linhaDup->id)
                            ->update([
                                'id_captacao_lote_movimentacao' => $manterId,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if ($manter !== null && $manter->transferencia_origem_id === null && $dup->transferencia_origem_id !== null) {
                    DB::table('captacao_lote_movimentacoes')
                        ->where('id', $manterId)
                        ->update(['transferencia_origem_id' => $dup->transferencia_origem_id]);
                    $manter = DB::table('captacao_lote_movimentacoes')->where('id', $manterId)->first();
                }

                DB::table('captacao_lote_movimentacoes')->where('id', $dup->id)->delete();
            }
        }

        DB::table('captacao_lote_movimentacoes')
            ->where('tipo', 'TRANSFERENCIA')
            ->whereNotNull('id_captacao_rota')
            ->update([
                'id_fruta' => null,
                'qtd_um' => null,
            ]);

        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->dropIndex('cap_lote_mov_rota_dem_idx');
            $table->index(
                ['id_captacao_lote', 'id_captacao_rota', 'tipo', 'id_unidade_negocio_origem'],
                'cap_lote_mov_rota_origem_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lote_movimentacoes', function (Blueprint $table): void {
            $table->dropIndex('cap_lote_mov_rota_origem_idx');
            $table->index(
                ['id_captacao_lote', 'id_captacao_rota', 'tipo', 'id_fruta', 'id_unidade_negocio_origem'],
                'cap_lote_mov_rota_dem_idx',
            );
        });

        Schema::dropIfExists('captacao_lote_movimentacao_linhas');
    }
};
