<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registros fixos exigidos pelo negócio (idempotente).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('estados')->upsert(
            [
                ['id' => 1, 'nome' => 'CEARA', 'descricao' => 'PAGA ICMS NA ENTRADA DO ESTADO', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'nome' => 'PERNAMBUCO', 'descricao' => 'PAGA ICMS NA VENDA', 'created_at' => $now, 'updated_at' => $now],
                ['id' => 3, 'nome' => 'ALAGOAS', 'descricao' => 'NAO PAGA ICMS', 'created_at' => $now, 'updated_at' => $now],
            ],
            ['id'],
            ['nome', 'descricao', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('estados')->whereIn('id', [1, 2, 3])->delete();
    }
};
