<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pedidos', 'captacao_concluida')) {
                $table->boolean('captacao_concluida')->default(false)->after('origem');
            }
        });

        Schema::table('clientes', function (Blueprint $table): void {
            if (! Schema::hasColumn('clientes', 'percentual_margem_alvo')) {
                $table->decimal('percentual_margem_alvo', 5, 2)->nullable()->after('desconto_nf');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            if (Schema::hasColumn('pedidos', 'captacao_concluida')) {
                $table->dropColumn('captacao_concluida');
            }
        });

        Schema::table('clientes', function (Blueprint $table): void {
            if (Schema::hasColumn('clientes', 'percentual_margem_alvo')) {
                $table->dropColumn('percentual_margem_alvo');
            }
        });
    }
};
