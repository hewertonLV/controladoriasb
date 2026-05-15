<?php

use App\Services\Empresas\EmpresaRegistryBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            try {
                Schema::table('empresas', function (Blueprint $table): void {
                    if (Schema::hasColumn('empresas', 'created_by')) {
                        $table->dropForeign(['created_by']);
                    }
                    if (Schema::hasColumn('empresas', 'updated_by')) {
                        $table->dropForeign(['updated_by']);
                    }
                });
            } catch (Throwable) {
            }

            if (Schema::hasTable('empresa_historicos')) {
                DB::table('empresa_historicos')->truncate();
            }

            DB::table('empresas')->delete();

            // SQLite: índices únicos devem ser removidos antes de dropColumn nas colunas indexadas.
            Schema::table('empresas', function (Blueprint $table): void {
                if (Schema::hasColumn('empresas', 'id_cigam')) {
                    try {
                        $table->dropUnique(['id_cigam']);
                    } catch (Throwable) {
                    }
                }
                if (Schema::hasColumn('empresas', 'cpf_cnpj')) {
                    try {
                        $table->dropUnique(['cpf_cnpj']);
                    } catch (Throwable) {
                    }
                }
                if (Schema::hasColumn('empresas', 'status')) {
                    try {
                        $table->dropIndex(['status']);
                    } catch (Throwable) {
                    }
                }
                if (Schema::hasColumn('empresas', 'tipo_pessoa')) {
                    try {
                        $table->dropIndex(['tipo_pessoa']);
                    } catch (Throwable) {
                    }
                }
            });

            Schema::table('empresas', function (Blueprint $table): void {
                $cols = [
                    'id_cigam',
                    'status',
                    'nome',
                    'fantasia',
                    'cpf_cnpj',
                    'unidade_negocio',
                    'tipo_pessoa',
                    'created_by',
                    'updated_by',
                ];
                $existing = array_values(array_filter(
                    $cols,
                    fn (string $c): bool => Schema::hasColumn('empresas', $c),
                ));
                if ($existing !== []) {
                    $table->dropColumn($existing);
                }

                $table->string('entidade_type');
                $table->unsignedBigInteger('entidade_id');
                $table->unique(['entidade_type', 'entidade_id']);
            });

            app(EmpresaRegistryBackfillService::class)->executar(registrarHistorico: false);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasTable('empresa_historicos')) {
                DB::table('empresa_historicos')->truncate();
            }

            DB::table('empresas')->delete();

            Schema::table('empresas', function (Blueprint $table): void {
                $table->dropUnique(['entidade_type', 'entidade_id']);
                $table->dropColumn(['entidade_type', 'entidade_id']);

                $table->string('id_cigam', 50)->unique();
                $table->boolean('status')->default(true);
                $table->string('nome', 255);
                $table->string('fantasia', 255)->nullable();
                $table->string('cpf_cnpj', 14)->unique();
                $table->unsignedInteger('unidade_negocio');
                $table->string('tipo_pessoa', 10);

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
