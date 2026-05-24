<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captacao_faturamento_dias', function (Blueprint $table): void {
            $table->id();
            $table->date('data_referencia');
            $table->foreignId('id_unidade_negocio_faturamento')
                ->constrained('unidades_negocio', indexName: 'cap_fat_dia_un_fat_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('status', 64)->default('CAPTACAO_ABERTA');
            $table->timestamp('finalizado_em')->nullable();
            $table->foreignId('finalizado_por_user_id')
                ->nullable()
                ->constrained('users', indexName: 'cap_fat_dia_user_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['data_referencia', 'id_unidade_negocio_faturamento'],
                'cap_fat_dia_data_un_uq',
            );
        });

        Schema::create('captacao_lotes', function (Blueprint $table): void {
            $table->id();
            $table->date('data_referencia');
            $table->foreignId('id_unidade_negocio_faturamento')
                ->constrained('unidades_negocio', indexName: 'cap_lote_un_fat_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_unidade_negocio_galpao')
                ->constrained('unidades_negocio', indexName: 'cap_lote_un_galp_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('tipo', 32)->default('CAPTACAO_PEDIDOS');
            $table->string('status', 64)->default('CAPTACAO_EM_ANDAMENTO');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['data_referencia', 'id_unidade_negocio_galpao'],
                'cap_lote_data_galp_uq',
            );
        });

        Schema::create('captacao_rotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_unidade_negocio_galpao')
                ->constrained('unidades_negocio', indexName: 'cap_rota_un_galp_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('nome', 120);
            $table->foreignId('id_veiculo')
                ->nullable()
                ->constrained('veiculos', indexName: 'cap_rota_veic_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pedidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_captacao_lote')
                ->constrained('captacao_lotes', indexName: 'pedido_cap_lote_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_cliente')
                ->constrained('clientes', indexName: 'pedido_cliente_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_captacao_rota')
                ->nullable()
                ->constrained('captacao_rotas', indexName: 'pedido_cap_rota_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->date('data_entrega')->nullable();
            $table->string('origem', 16)->default('WEB');
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users', indexName: 'pedido_user_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['id_captacao_lote', 'id_cliente'], 'pedido_lote_cliente_uq');
        });

        Schema::create('pedido_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_pedido')
                ->constrained('pedidos', indexName: 'ped_item_pedido_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_fruta')
                ->constrained('frutas', indexName: 'ped_item_fruta_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('quantidade', 15, 3)->default(0);
            $table->decimal('preco_venda', 15, 4)->nullable();
            $table->decimal('custo_referencia', 15, 4)->nullable();
            $table->foreignId('id_unidade_origem_fisica')
                ->nullable()
                ->constrained('unidades_negocio', indexName: 'ped_item_un_orig_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['id_pedido', 'id_fruta'], 'ped_item_ped_fruta_uq');
        });

        Schema::create('cliente_fruta_vinculos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_cliente')
                ->constrained('clientes', indexName: 'cli_fruta_vinc_cli_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_fruta')
                ->constrained('frutas', indexName: 'cli_fruta_vinc_fruta_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['id_cliente', 'id_fruta'], 'cli_fruta_vinc_uq');
        });

        Schema::create('pedido_historicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_pedido')
                ->constrained('pedidos', indexName: 'ped_hist_pedido_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('acao', 32);
            $table->string('origem', 16);
            $table->json('payload')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', indexName: 'ped_hist_user_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pedido_item_historicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_pedido_item')
                ->constrained('pedido_itens', indexName: 'ped_it_hist_item_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('acao', 32);
            $table->string('origem', 16);
            $table->json('payload')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', indexName: 'ped_it_hist_user_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('captacao_lote_cigan_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_captacao_lote')
                ->constrained('captacao_lotes', indexName: 'cap_cigan_lote_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->string('versao_layout', 16)->default('v0');
            $table->string('caminho_arquivo', 255);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', indexName: 'cap_cigan_user_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('captacao_lote_frete_linhas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_captacao_lote')
                ->constrained('captacao_lotes', indexName: 'cap_frete_lote_fk')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('id_fruta')
                ->constrained('frutas', indexName: 'cap_frete_fruta_fk')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_frete')
                ->nullable()
                ->constrained('fretes', indexName: 'cap_frete_frete_fk')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['id_captacao_lote', 'id_fruta'], 'cap_frete_lote_fruta_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captacao_lote_frete_linhas');
        Schema::dropIfExists('captacao_lote_cigan_exports');
        Schema::dropIfExists('pedido_item_historicos');
        Schema::dropIfExists('pedido_historicos');
        Schema::dropIfExists('cliente_fruta_vinculos');
        Schema::dropIfExists('pedido_itens');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('captacao_rotas');
        Schema::dropIfExists('captacao_lotes');
        Schema::dropIfExists('captacao_faturamento_dias');
    }
};
