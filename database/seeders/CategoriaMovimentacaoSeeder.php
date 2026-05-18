<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registros fixos idempotentes para categorias de movimentação de estoque.
 *
 * IDs estáveis conforme especificação de negócio (reseed / migrate:fresh --seed).
 */
class CategoriaMovimentacaoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            ['id' => 1, 'nome' => 'COMPRA', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'nome' => 'TRANSFERENCIA', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'nome' => 'VENDA', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'nome' => 'DOACAO', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'nome' => 'DESCARTE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'nome' => 'DEVOLUCAO', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'nome' => 'CONVERSAO EMBALAGEM', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::transaction(function () use ($rows): void {
            DB::table('categorias_movimentacao')->upsert(
                $rows,
                ['id'],
                ['nome', 'updated_at'],
            );
        });
    }
}
