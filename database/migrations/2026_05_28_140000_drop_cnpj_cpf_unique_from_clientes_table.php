<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clientes', 'cnpj_cpf')) {
            return;
        }

        $this->dropCnpjCpfUniqueIndex();

        Schema::table('clientes', function (Blueprint $table): void {
            if (! $this->hasNonUniqueIndexOnCnpjCpf()) {
                $table->index('cnpj_cpf');
            }
        });
    }

    public function down(): void
    {
        // Não recriamos unique: ADR-0062 permite documento repetido entre clientes.
    }

    private function dropCnpjCpfUniqueIndex(): void
    {
        try {
            foreach (Schema::getIndexes('clientes') as $index) {
                $columns = $index['columns'] ?? [];
                $unique = (bool) ($index['unique'] ?? false);
                $name = (string) ($index['name'] ?? '');

                if ($unique && $name !== '' && $columns === ['cnpj_cpf']) {
                    Schema::table('clientes', function (Blueprint $table) use ($name): void {
                        $table->dropUnique($name);
                    });
                }
            }
        } catch (Throwable) {
            foreach (['clientes_cnpj_cpf_unique', 'cnpj_cpf'] as $name) {
                try {
                    Schema::table('clientes', function (Blueprint $table) use ($name): void {
                        $table->dropUnique($name);
                    });
                } catch (Throwable) {
                    // Index not present on this connection.
                }
            }
        }
    }

    private function hasNonUniqueIndexOnCnpjCpf(): bool
    {
        try {
            foreach (Schema::getIndexes('clientes') as $index) {
                $columns = $index['columns'] ?? [];
                $unique = (bool) ($index['unique'] ?? false);

                if (! $unique && $columns === ['cnpj_cpf']) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
};
