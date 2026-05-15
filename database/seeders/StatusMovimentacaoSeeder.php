<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registros fixos idempotentes para status de movimentação de estoque.
 *
 * IDs estáveis: 1 = ENTRADA, 2 = SAÍDA (preservados em reseed / migrate:fresh --seed).
 */
class StatusMovimentacaoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            ['id' => 1, 'nome' => 'ENTRADA', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'nome' => 'SAIDA', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::transaction(function () use ($rows): void {
            DB::table('status_movimentacoes')->upsert(
                $rows,
                ['id'],
                ['nome', 'updated_at'],
            );
        });
    }
}
