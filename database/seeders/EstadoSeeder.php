<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registros fixos idempotentes de estados (ICMS).
 */
class EstadoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            DB::table('estados')->upsert(
                [
                    ['id' => 1, 'nome' => 'CEARA', 'descricao' => 'PAGA ICMS NA ENTRADA DO ESTADO', 'created_at' => $now, 'updated_at' => $now],
                    ['id' => 2, 'nome' => 'PERNAMBUCO', 'descricao' => 'PAGA ICMS NA VENDA', 'created_at' => $now, 'updated_at' => $now],
                    ['id' => 3, 'nome' => 'ALAGOAS', 'descricao' => 'NAO PAGA ICMS', 'created_at' => $now, 'updated_at' => $now],
                ],
                ['id'],
                ['nome', 'descricao', 'updated_at'],
            );
        });
    }
}
