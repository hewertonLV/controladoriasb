<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unidades_negocio', 'cpf_cnpj')) {
            return;
        }

        $this->dropCpfCnpjUniqueIndex();

        DB::table('unidades_negocio')
            ->where('cpf_cnpj', '')
            ->orWhere('cpf_cnpj', '00000000000')
            ->update(['cpf_cnpj' => null]);

        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->string('cpf_cnpj', 14)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('unidades_negocio', 'cpf_cnpj')) {
            return;
        }

        DB::table('unidades_negocio')
            ->whereNull('cpf_cnpj')
            ->update(['cpf_cnpj' => '00000000000']);

        Schema::table('unidades_negocio', function (Blueprint $table) {
            $table->string('cpf_cnpj', 14)->default('00000000000')->nullable(false)->change();
        });
    }

    private function dropCpfCnpjUniqueIndex(): void
    {
        try {
            foreach (Schema::getIndexes('unidades_negocio') as $index) {
                $columns = $index['columns'] ?? [];
                $unique = (bool) ($index['unique'] ?? false);
                $name = (string) ($index['name'] ?? '');

                if ($unique && $name !== '' && $columns === ['cpf_cnpj']) {
                    Schema::table('unidades_negocio', function (Blueprint $table) use ($name) {
                        $table->dropUnique($name);
                    });
                }
            }
        } catch (Throwable) {
            foreach (['unidades_negocio_cpf_cnpj_unique', 'cpf_cnpj'] as $name) {
                try {
                    Schema::table('unidades_negocio', function (Blueprint $table) use ($name) {
                        $table->dropUnique($name);
                    });
                } catch (Throwable) {
                    // Index not present on this connection.
                }
            }
        }
    }
};
