<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_c_o_un_ng', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_unidade_negocio')
                ->constrained('unidades_negocio')
                ->cascadeOnDelete();
            $table->decimal('custo_operacional', 15, 2);
            $table->boolean('status_position')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['id_unidade_negocio', 'status_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_c_o_un_ng');
    }
};
