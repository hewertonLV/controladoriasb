<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->unsignedBigInteger('movimentacao_origem_id')->nullable()->after('categoria_movimentacao_id');
            $table->unsignedBigInteger('substituida_por_id')->nullable()->after('movimentacao_origem_id');
            $table->unsignedInteger('versao')->default(1)->after('substituida_por_id');
            $table->string('status_registro', 20)->default('ATIVO')->after('versao');
            $table->text('motivo_substituicao')->nullable()->after('status_registro');
            $table->timestamp('substituida_em')->nullable()->after('motivo_substituicao');
            $table->timestamp('data_movimentacao')->nullable()->after('substituida_em');
        });

        foreach (DB::table('movimentacoes')->select('id', 'created_at')->cursor() as $row) {
            DB::table('movimentacoes')->where('id', $row->id)->update([
                'versao' => 1,
                'status_registro' => 'ATIVO',
                'data_movimentacao' => $row->created_at,
            ]);
        }

        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->foreign('movimentacao_origem_id')
                ->references('id')
                ->on('movimentacoes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('substituida_por_id')
                ->references('id')
                ->on('movimentacoes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index('movimentacao_origem_id');
            $table->index('substituida_por_id');
            $table->index('status_registro');
            $table->index('data_movimentacao');
            $table->index(['categoria_movimentacao_id', 'status_registro']);
            $table->index(['id_frete', 'status_registro']);
        });
    }

    public function down(): void
    {
        Schema::table('movimentacoes', function (Blueprint $table) {
            $table->dropForeign(['movimentacao_origem_id']);
            $table->dropForeign(['substituida_por_id']);
            $table->dropIndex(['movimentacao_origem_id']);
            $table->dropIndex(['substituida_por_id']);
            $table->dropIndex(['status_registro']);
            $table->dropIndex(['data_movimentacao']);
            $table->dropIndex(['categoria_movimentacao_id', 'status_registro']);
            $table->dropIndex(['id_frete', 'status_registro']);
            $table->dropColumn([
                'movimentacao_origem_id',
                'substituida_por_id',
                'versao',
                'status_registro',
                'motivo_substituicao',
                'substituida_em',
                'data_movimentacao',
            ]);
        });
    }
};
