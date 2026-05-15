<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clientes', 'id_praca')) {
            $this->dropIdPracaForeignKeyIfExists();
            $this->setClientesIdPracaNullable(true);
        } else {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unsignedBigInteger('id_praca')->nullable()->after('id_unidade_negocio');
            });
        }

        // SQL portável (SQLite não aceita UPDATE ... JOIN do MySQL).
        DB::statement(
            'UPDATE clientes SET id_praca = NULL
             WHERE id_praca IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM pracas WHERE pracas.id = clientes.id_praca)',
        );

        DB::statement(
            'UPDATE clientes SET id_praca = (
                 SELECT MIN(p.id) FROM pracas p WHERE p.id_unidade_negocio = clientes.id_unidade_negocio
             )
             WHERE id_praca IS NULL',
        );

        $this->ensurePadraoPracaForClientesOrphans();

        $this->setClientesIdPracaNullable(false);

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreign('id_praca')
                ->references('id')
                ->on('pracas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_praca');
        });
    }

    private function dropIdPracaForeignKeyIfExists(): void
    {
        try {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropForeign(['id_praca']);
            });
        } catch (Throwable) {
            //
        }
    }

    private function setClientesIdPracaNullable(bool $nullable): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE clientes MODIFY id_praca BIGINT UNSIGNED {$nullSql}");
        } else {
            Schema::table('clientes', function (Blueprint $table) use ($nullable) {
                $table->unsignedBigInteger('id_praca')->nullable($nullable)->change();
            });
        }
    }

    /**
     * Garante uma praça por unidade quando ainda existir cliente sem `id_praca`
     * (ex.: unidade sem praças cadastradas).
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function ensurePadraoPracaForClientesOrphans(): void
    {
        $idUnidades = DB::table('clientes')
            ->whereNull('id_praca')
            ->distinct()
            ->pluck('id_unidade_negocio');

        foreach ($idUnidades as $idUn) {
            if ($idUn === null || (int) $idUn < 1) {
                continue;
            }

            $idUn = (int) $idUn;
            $now = now();

            $pracaId = DB::table('pracas')->insertGetId([
                'nome' => "PADRAO MIGRACAO {$idUn}",
                'id_unidade_negocio' => $idUn,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('clientes')
                ->where('id_unidade_negocio', $idUn)
                ->whereNull('id_praca')
                ->update(['id_praca' => $pracaId]);
        }
    }
};
