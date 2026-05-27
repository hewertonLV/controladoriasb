<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captacao_carteiras', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 120);
            $table->foreignId('id_unidade_negocio_faturamento')
                ->constrained('unidades_negocio', indexName: 'cap_cart_un_fat_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_unidade_negocio_galpao')
                ->constrained('unidades_negocio', indexName: 'cap_cart_un_galp_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao'],
                'cap_cart_fat_galp_uq',
            );
        });

        Schema::table('clientes', function (Blueprint $table): void {
            $table->foreignId('id_captacao_carteira')
                ->nullable()
                ->after('id_unidade_negocio')
                ->constrained('captacao_carteiras', indexName: 'cli_cap_cart_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::create('cliente_captacao_agenda', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cliente')
                ->constrained('clientes', indexName: 'cli_cap_ag_cli_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            $table->string('tipo', 32);
            $table->timestamps();

            $table->unique(
                ['id_cliente', 'dia_semana', 'tipo'],
                'cli_cap_ag_uq',
            );
        });

        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->foreignId('id_captacao_carteira')
                ->nullable()
                ->after('data_referencia')
                ->constrained('captacao_carteiras', indexName: 'cap_lote_cart_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $this->backfillCarteirasELotes();

        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->dropUnique('cap_lote_data_galp_uq');
            $table->unique(
                ['data_referencia', 'id_captacao_carteira'],
                'cap_lote_data_cart_uq',
            );
        });
    }

    public function down(): void
    {
        Schema::table('captacao_lotes', function (Blueprint $table): void {
            $table->dropUnique('cap_lote_data_cart_uq');
            $table->unique(
                ['data_referencia', 'id_unidade_negocio_galpao'],
                'cap_lote_data_galp_uq',
            );
            $table->dropConstrainedForeignId('id_captacao_carteira');
        });

        Schema::dropIfExists('cliente_captacao_agenda');

        Schema::table('clientes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_captacao_carteira');
        });

        Schema::dropIfExists('captacao_carteiras');
    }

    private function backfillCarteirasELotes(): void
    {
        $pares = DB::table('captacao_lotes')
            ->select('id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao')
            ->distinct()
            ->get();

        $mapa = [];

        foreach ($pares as $par) {
            $fat = (int) $par->id_unidade_negocio_faturamento;
            $galp = (int) $par->id_unidade_negocio_galpao;
            $chave = "{$fat}:{$galp}";

            if (isset($mapa[$chave])) {
                continue;
            }

            $nomeFat = DB::table('unidades_negocio')->where('id', $fat)->value('nome') ?? "UN {$fat}";
            $nomeGalp = DB::table('unidades_negocio')->where('id', $galp)->value('nome') ?? "Galpão {$galp}";

            $id = DB::table('captacao_carteiras')->insertGetId([
                'nome' => mb_strtoupper("{$nomeFat} / {$nomeGalp}", 'UTF-8'),
                'id_unidade_negocio_faturamento' => $fat,
                'id_unidade_negocio_galpao' => $galp,
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $mapa[$chave] = $id;
        }

        foreach (DB::table('captacao_lotes')->get(['id', 'id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao']) as $lote) {
            $chave = "{$lote->id_unidade_negocio_faturamento}:{$lote->id_unidade_negocio_galpao}";
            $carteiraId = $mapa[$chave] ?? null;

            if ($carteiraId !== null) {
                DB::table('captacao_lotes')->where('id', $lote->id)->update([
                    'id_captacao_carteira' => $carteiraId,
                ]);
            }
        }

        DB::table('clientes')
            ->whereNotNull('id_unidade_negocio')
            ->orderBy('id')
            ->chunkById(200, function ($clientes) use (&$mapa): void {
                foreach ($clientes as $cliente) {
                    if ($cliente->id_captacao_carteira !== null) {
                        continue;
                    }

                    $carteiraId = DB::table('captacao_carteiras')
                        ->where('id_unidade_negocio_faturamento', $cliente->id_unidade_negocio)
                        ->orderBy('id')
                        ->value('id');

                    if ($carteiraId !== null) {
                        DB::table('clientes')->where('id', $cliente->id)->update([
                            'id_captacao_carteira' => $carteiraId,
                        ]);
                    }
                }
            });
    }
};
