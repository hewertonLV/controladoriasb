<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captacao_lote_rotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_captacao_lote')
                ->constrained('captacao_lotes', indexName: 'cap_lote_rota_lote_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_captacao_rota')
                ->constrained('captacao_rotas', indexName: 'cap_lote_rota_rota_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('nome_motorista', 120)->nullable();
            $table->foreignId('id_veiculo')
                ->nullable()
                ->constrained('veiculos', indexName: 'cap_lote_rota_veic_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['id_captacao_lote', 'id_captacao_rota'],
                'cap_lote_rota_uq',
            );
        });

        if (! Schema::hasTable('pedidos') || ! Schema::hasTable('captacao_rotas')) {
            return;
        }

        $pares = DB::table('pedidos as p')
            ->join('captacao_rotas as r', 'r.id', '=', 'p.id_captacao_rota')
            ->whereNotNull('p.id_captacao_rota')
            ->where(function ($q): void {
                $q->whereNotNull('r.nome_motorista')
                    ->orWhereNotNull('r.id_veiculo');
            })
            ->select([
                'p.id_captacao_lote',
                'p.id_captacao_rota',
                'r.nome_motorista',
                'r.id_veiculo',
            ])
            ->distinct()
            ->get();

        $agora = now();

        foreach ($pares as $par) {
            DB::table('captacao_lote_rotas')->insertOrIgnore([
                'id_captacao_lote' => $par->id_captacao_lote,
                'id_captacao_rota' => $par->id_captacao_rota,
                'nome_motorista' => $par->nome_motorista,
                'id_veiculo' => $par->id_veiculo,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('captacao_lote_rotas');
    }
};
