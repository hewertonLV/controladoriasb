<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('contato_nome', 255)->nullable()->after('fantasia');
            $table->string('contato_telefone', 15)->nullable()->after('contato_nome');
            $table->string('contato_email', 255)->nullable()->after('contato_telefone');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['contato_nome', 'contato_telefone', 'contato_email']);
        });
    }
};
