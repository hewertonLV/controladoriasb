<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('captacao_rotas', 'id_captacao_carteira')) {
            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->unsignedBigInteger('id_captacao_carteira')->nullable()->after('id');
            });
        }

        $this->garantirForeignKeyCarteira('id_captacao_carteira', 'cap_rota_cart_fk');

        if (Schema::hasColumn('captacao_rotas', 'id_unidade_negocio_galpao')) {
            foreach (DB::table('captacao_rotas')->orderBy('id')->cursor() as $rota) {
                if ($rota->id_captacao_carteira !== null) {
                    continue;
                }

                $carteiraId = DB::table('captacao_carteiras')
                    ->where('id_unidade_negocio_galpao', $rota->id_unidade_negocio_galpao)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->value('id');

                if ($carteiraId !== null) {
                    DB::table('captacao_rotas')
                        ->where('id', $rota->id)
                        ->update(['id_captacao_carteira' => $carteiraId]);
                }
            }

            $this->dropForeignKeysForColumn('captacao_rotas', 'id_unidade_negocio_galpao');

            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->dropColumn('id_unidade_negocio_galpao');
            });
        }

        if (Schema::hasColumn('captacao_rotas', 'id_captacao_carteira')) {
            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->unsignedBigInteger('id_captacao_carteira')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('captacao_rotas', 'id_unidade_negocio_galpao')) {
            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->unsignedBigInteger('id_unidade_negocio_galpao')->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('captacao_rotas', 'id_captacao_carteira')) {
            foreach (DB::table('captacao_rotas')->orderBy('id')->cursor() as $rota) {
                if ($rota->id_captacao_carteira === null) {
                    continue;
                }

                $galpaoId = DB::table('captacao_carteiras')
                    ->where('id', $rota->id_captacao_carteira)
                    ->value('id_unidade_negocio_galpao');

                if ($galpaoId !== null) {
                    DB::table('captacao_rotas')
                        ->where('id', $rota->id)
                        ->update(['id_unidade_negocio_galpao' => $galpaoId]);
                }
            }
        }

        if (Schema::hasColumn('captacao_rotas', 'id_captacao_carteira')) {
            $this->dropForeignKeysForColumn('captacao_rotas', 'id_captacao_carteira');

            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->dropColumn('id_captacao_carteira');
            });
        }

        if (Schema::hasColumn('captacao_rotas', 'id_unidade_negocio_galpao')) {
            Schema::table('captacao_rotas', function (Blueprint $table): void {
                $table->unsignedBigInteger('id_unidade_negocio_galpao')->nullable(false)->change();
            });

            $this->garantirForeignKeyGalpao('id_unidade_negocio_galpao', 'cap_rota_un_galp_fk');
        }
    }

    private function garantirForeignKeyCarteira(string $column, string $foreignName): void
    {
        if (! Schema::hasColumn('captacao_rotas', $column)) {
            return;
        }

        if ($this->foreignKeyExists('captacao_rotas', $column, $foreignName)) {
            return;
        }

        Schema::table('captacao_rotas', function (Blueprint $table) use ($column, $foreignName): void {
            $table->foreign($column, $foreignName)
                ->references('id')
                ->on('captacao_carteiras')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    private function garantirForeignKeyGalpao(string $column, string $foreignName): void
    {
        if (! Schema::hasColumn('captacao_rotas', $column)) {
            return;
        }

        if ($this->foreignKeyExists('captacao_rotas', $column, $foreignName)) {
            return;
        }

        Schema::table('captacao_rotas', function (Blueprint $table) use ($column, $foreignName): void {
            $table->foreign($column, $foreignName)
                ->references('id')
                ->on('unidades_negocio')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    private function foreignKeyExists(string $table, string $column, string $foreignName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA foreign_key_list({$table})");

            foreach ($rows as $row) {
                if (($row->from ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $foreignName, 'FOREIGN KEY'],
        );

        return $row !== null;
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropForeign([$column]);
                });
            } catch (\Throwable) {
                // FK já removida ou inexistente no ambiente de testes.
            }

            return;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column],
        );

        foreach ($constraints as $constraint) {
            Schema::table($table, function (Blueprint $blueprint) use ($constraint): void {
                $blueprint->dropForeign($constraint->CONSTRAINT_NAME);
            });
        }
    }
};
