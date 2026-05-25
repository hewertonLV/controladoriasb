<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('captacao_carteiras')) {
            return;
        }

        Schema::table('captacao_carteiras', function (Blueprint $table): void {
            if (! $this->hasIndex('captacao_carteiras', 'cap_cart_un_fat_ix')) {
                $table->index('id_unidade_negocio_faturamento', 'cap_cart_un_fat_ix');
            }
            if (! $this->hasIndex('captacao_carteiras', 'cap_cart_un_galp_ix')) {
                $table->index('id_unidade_negocio_galpao', 'cap_cart_un_galp_ix');
            }
        });

        Schema::table('captacao_carteiras', function (Blueprint $table): void {
            if ($this->hasIndex('captacao_carteiras', 'cap_cart_fat_galp_uq')) {
                $table->dropUnique('cap_cart_fat_galp_uq');
            }
            if (! $this->hasIndex('captacao_carteiras', 'cap_cart_fat_galp_ix')) {
                $table->index(
                    ['id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao'],
                    'cap_cart_fat_galp_ix',
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('captacao_carteiras')) {
            return;
        }

        Schema::table('captacao_carteiras', function (Blueprint $table): void {
            if ($this->hasIndex('captacao_carteiras', 'cap_cart_fat_galp_ix')) {
                $table->dropIndex('cap_cart_fat_galp_ix');
            }
            if (! $this->hasIndex('captacao_carteiras', 'cap_cart_fat_galp_uq')) {
                $table->unique(
                    ['id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao'],
                    'cap_cart_fat_galp_uq',
                );
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $definition) {
            if (($definition['name'] ?? '') === $index) {
                return true;
            }
        }

        return false;
    }
};
