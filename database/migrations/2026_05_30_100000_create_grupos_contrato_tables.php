<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clientes', 'desconto_contrato')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('desconto_contrato');
            });
        }

        Schema::create('grupos_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('grupo_contrato_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_contrato_id')->constrained('grupos_contrato')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('competencia_inicio', 7);
            $table->string('competencia_fim', 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['grupo_contrato_id', 'competencia_inicio', 'competencia_fim'], 'gcc_grupo_competencia_idx');
            $table->index(['cliente_id', 'competencia_inicio', 'competencia_fim'], 'gcc_cliente_competencia_idx');
        });

        Schema::create('grupo_contrato_descontos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_contrato_id')->constrained('grupos_contrato')->cascadeOnDelete();
            $table->string('competencia', 7);
            $table->decimal('valor', 15, 2)->default(0);
            $table->decimal('valor_teto', 15, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['grupo_contrato_id', 'competencia'], 'gcd_grupo_competencia_unique');
        });

        Schema::create('grupo_contrato_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_contrato_id')->constrained('grupos_contrato')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origem', 30);
            $table->string('acao', 30);
            $table->json('dados_antes')->nullable();
            $table->json('dados_depois')->nullable();
            $table->json('alteracoes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['grupo_contrato_id', 'created_at'], 'gch_grupo_created_idx');
        });

        Schema::create('grupo_contrato_cliente_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_contrato_cliente_id')
                ->nullable()
                ->constrained('grupo_contrato_clientes', indexName: 'gcch_membro_fk')
                ->nullOnDelete();
            $table->foreignId('grupo_contrato_id')->constrained('grupos_contrato')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origem', 30);
            $table->string('acao', 30);
            $table->json('dados_antes')->nullable();
            $table->json('dados_depois')->nullable();
            $table->json('alteracoes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['grupo_contrato_id', 'cliente_id', 'created_at'], 'gcch_grupo_cliente_created_idx');
        });

        Schema::create('grupo_contrato_desconto_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_contrato_desconto_id')
                ->nullable()
                ->constrained('grupo_contrato_descontos', indexName: 'gcdh_desconto_fk')
                ->nullOnDelete();
            $table->foreignId('grupo_contrato_id')->constrained('grupos_contrato')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origem', 30);
            $table->string('acao', 30);
            $table->json('dados_antes')->nullable();
            $table->json('dados_depois')->nullable();
            $table->json('alteracoes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['grupo_contrato_id', 'created_at'], 'gcdh_grupo_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_contrato_desconto_historicos');
        Schema::dropIfExists('grupo_contrato_cliente_historicos');
        Schema::dropIfExists('grupo_contrato_historicos');
        Schema::dropIfExists('grupo_contrato_descontos');
        Schema::dropIfExists('grupo_contrato_clientes');
        Schema::dropIfExists('grupos_contrato');

        if (! Schema::hasColumn('clientes', 'desconto_contrato')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->decimal('desconto_contrato', 15, 2)->default(0)->after('desconto_nf');
            });
        }
    }
};
