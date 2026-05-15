<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->renameColumn('valor_nf_cx', 'valor_nf_um');
        });
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->renameColumn('valor_frete_cx', 'valor_frete_um');
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->renameColumn('valor_nf_um', 'valor_nf_cx');
        });
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->renameColumn('valor_frete_um', 'valor_frete_cx');
        });
    }
};
