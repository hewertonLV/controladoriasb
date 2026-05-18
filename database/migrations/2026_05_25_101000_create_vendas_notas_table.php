<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendas_notas')) {
            return;
        }

        Schema::create('vendas_notas', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_nf');
            $table->foreignId('id_empresa_origem')->constrained('empresas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('id_empresa_destino')->constrained('empresas')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('id_unidade_negocio_faturamento')->constrained('unidades_negocio')->cascadeOnUpdate()->restrictOnDelete();
            $table->dateTime('data_emissao')->useCurrent();
            $table->decimal('valor_total_nf', 15, 2)->default(0);
            $table->string('status_registro', 20)->default('ATIVO');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['numero_nf', 'status_registro']);
            $table->index(['id_empresa_origem', 'id_empresa_destino']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_notas');
    }
};
