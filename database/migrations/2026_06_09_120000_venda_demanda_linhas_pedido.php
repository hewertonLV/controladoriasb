<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
            if (! Schema::hasColumn('captacao_lote_movimentacao_linhas', 'id_pedido')) {
                $table->foreignId('id_pedido')
                    ->nullable()
                    ->after('id_captacao_lote_movimentacao')
                    ->constrained('pedidos', indexName: 'cap_lote_mov_lin_ped_fk')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('captacao_lote_movimentacao_linhas', 'preco_venda')) {
                $table->decimal('preco_venda', 15, 2)->nullable()->after('qtd_um');
            }
        });

        Schema::disableForeignKeyConstraints();

        if (! $this->indexExists('captacao_lote_movimentacao_linhas', 'cap_lote_mov_lin_mov_idx')) {
            Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
                $table->index('id_captacao_lote_movimentacao', 'cap_lote_mov_lin_mov_idx');
            });
        }

        if ($this->indexExists('captacao_lote_movimentacao_linhas', 'cap_lote_mov_lin_mov_fruta_uq')) {
            Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
                $table->dropUnique('cap_lote_mov_lin_mov_fruta_uq');
            });
        }

        if (! Schema::hasColumn('captacao_lote_movimentacao_linhas', 'id_pedido_key')) {
            Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
                $table->unsignedBigInteger('id_pedido_key')->default(0)->after('id_pedido');
            });

            DB::table('captacao_lote_movimentacao_linhas')->update([
                'id_pedido_key' => DB::raw('COALESCE(id_pedido, 0)'),
            ]);
        }

        if (! $this->indexExists('captacao_lote_movimentacao_linhas', 'cap_lote_mov_lin_mov_fruta_ped_uq')) {
            Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
                $table->unique(
                    ['id_captacao_lote_movimentacao', 'id_fruta', 'id_pedido_key'],
                    'cap_lote_mov_lin_mov_fruta_ped_uq',
                );
            });
        }

        Schema::enableForeignKeyConstraints();

        $this->consolidarVendasPorRota();
    }

    public function down(): void
    {
        Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
            $table->dropUnique('cap_lote_mov_lin_mov_fruta_ped_uq');
        });

        DB::statement('ALTER TABLE captacao_lote_movimentacao_linhas DROP COLUMN id_pedido_key');

        Schema::table('captacao_lote_movimentacao_linhas', function (Blueprint $table): void {
            $table->unique(
                ['id_captacao_lote_movimentacao', 'id_fruta'],
                'cap_lote_mov_lin_mov_fruta_uq',
            );
            $table->dropConstrainedForeignId('id_pedido');
            $table->dropColumn('preco_venda');
        });
    }

    private function consolidarVendasPorRota(): void
    {
        $grupos = DB::table('captacao_lote_movimentacoes')
            ->select(['id_captacao_lote', 'id_captacao_rota'])
            ->where('tipo', 'VENDA_NOTA')
            ->whereNotNull('id_captacao_rota')
            ->whereNotNull('id_pedido')
            ->groupBy('id_captacao_lote', 'id_captacao_rota')
            ->get();

        foreach ($grupos as $grupo) {
            $legacyRows = DB::table('captacao_lote_movimentacoes')
                ->where('tipo', 'VENDA_NOTA')
                ->where('id_captacao_lote', $grupo->id_captacao_lote)
                ->where('id_captacao_rota', $grupo->id_captacao_rota)
                ->whereNotNull('id_pedido')
                ->orderBy('id')
                ->get();

            if ($legacyRows->isEmpty()) {
                continue;
            }

            $header = DB::table('captacao_lote_movimentacoes')
                ->where('tipo', 'VENDA_NOTA')
                ->where('id_captacao_lote', $grupo->id_captacao_lote)
                ->where('id_captacao_rota', $grupo->id_captacao_rota)
                ->whereNull('id_pedido')
                ->first();

            if ($header !== null) {
                $headerId = (int) $header->id;

                foreach ($legacyRows as $row) {
                    $this->copiarLinhasPedidoParaDemanda((int) $row->id_pedido, $headerId);
                    DB::table('captacao_lote_movimentacoes')->where('id', $row->id)->delete();
                }

                continue;
            }

            $keeper = $legacyRows->first();
            $headerId = (int) $keeper->id;

            $this->copiarLinhasPedidoParaDemanda((int) $keeper->id_pedido, $headerId);

            DB::table('captacao_lote_movimentacoes')
                ->where('id', $headerId)
                ->update([
                    'id_pedido' => null,
                    'venda_nota_id' => null,
                    'updated_at' => now(),
                ]);

            foreach ($legacyRows->slice(1) as $row) {
                $this->copiarLinhasPedidoParaDemanda((int) $row->id_pedido, $headerId);
                DB::table('captacao_lote_movimentacoes')->where('id', $row->id)->delete();
            }
        }
    }

    private function copiarLinhasPedidoParaDemanda(int $idPedido, int $headerId): void
    {
        $itens = DB::table('pedido_itens')
            ->where('id_pedido', $idPedido)
            ->where('quantidade', '>', 0)
            ->get();

        foreach ($itens as $item) {
            $existente = DB::table('captacao_lote_movimentacao_linhas')
                ->where('id_captacao_lote_movimentacao', $headerId)
                ->where('id_fruta', $item->id_fruta)
                ->where('id_pedido', $idPedido)
                ->first();

            $qtd = round((float) $item->quantidade, 3);
            $preco = $item->preco_venda !== null ? round((float) $item->preco_venda, 2) : null;

            if ($existente !== null) {
                DB::table('captacao_lote_movimentacao_linhas')
                    ->where('id', $existente->id)
                    ->update([
                        'qtd_um' => $qtd,
                        'preco_venda' => $preco,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('captacao_lote_movimentacao_linhas')->insert([
                'id_captacao_lote_movimentacao' => $headerId,
                'id_pedido' => $idPedido,
                'id_pedido_key' => $idPedido,
                'id_fruta' => $item->id_fruta,
                'qtd_um' => $qtd,
                'preco_venda' => $preco,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $rows = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$index]);

        return $rows !== [];
    }
};
