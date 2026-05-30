<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencia_demandas', function (Blueprint $table): void {
            $table->id();
            $table->string('origem', 20)->default('MANUAL');
            $table->string('status', 30)->default('DEMANDA_CRIADA');
            $table->foreignId('id_unidade_negocio_origem')
                ->constrained('unidades_negocio', indexName: 'tr_dem_un_orig_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_unidade_negocio_destino')
                ->constrained('unidades_negocio', indexName: 'tr_dem_un_dest_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('observacao', 500)->nullable();
            $table->foreignId('id_frete')->nullable()->constrained('fretes', indexName: 'tr_dem_frete_fk')->nullOnDelete();
            $table->string('nf_transferencia_path')->nullable();
            $table->unsignedBigInteger('transferencia_origem_id')->nullable();
            $table->timestamps();

            $table->index('status', 'tr_dem_status_idx');
        });

        Schema::create('transferencia_demanda_linhas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_transferencia_demanda')
                ->constrained('transferencia_demandas', indexName: 'tr_dem_lin_dem_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_fruta')
                ->constrained('frutas', indexName: 'tr_dem_lin_fruta_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('qtd_um', 15, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencia_demanda_linhas');
        Schema::dropIfExists('transferencia_demandas');
    }
};
