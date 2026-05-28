<?php

use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            if (! Schema::hasColumn('clientes', 'id_unidade_negocio_saida_fisico_padrao')) {
                $table->foreignId('id_unidade_negocio_saida_fisico_padrao')
                    ->nullable()
                    ->after('percentual_margem_alvo')
                    ->constrained('unidades_negocio', indexName: 'clientes_saida_fis_pad_un_fk')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        });

        if (Schema::hasColumn('clientes', 'saida_estoque_fisico_padrao')) {
            Cliente::query()->eachById(function (Cliente $cliente): void {
                $preferencia = (string) ($cliente->getAttributes()['saida_estoque_fisico_padrao'] ?? 'galpao');

                $idUnidade = match ($preferencia) {
                    'hub' => UnidadeNegocio::query()->ativas()->where('is_hub', true)->orderBy('nome')->value('id'),
                    default => CaptacaoCarteira::query()
                        ->where('ativo', true)
                        ->where('id_unidade_negocio_faturamento', $cliente->id_unidade_negocio)
                        ->orderBy('id')
                        ->value('id_unidade_negocio_galpao'),
                };

                if ($idUnidade !== null) {
                    Cliente::query()
                        ->whereKey($cliente->id)
                        ->update(['id_unidade_negocio_saida_fisico_padrao' => (int) $idUnidade]);
                }
            });

            Schema::table('clientes', function (Blueprint $table): void {
                $table->dropColumn('saida_estoque_fisico_padrao');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table): void {
            if (! Schema::hasColumn('clientes', 'saida_estoque_fisico_padrao')) {
                $table->string('saida_estoque_fisico_padrao', 10)
                    ->default('galpao')
                    ->after('percentual_margem_alvo');
            }
        });

        if (Schema::hasColumn('clientes', 'id_unidade_negocio_saida_fisico_padrao')) {
            Cliente::query()->eachById(function (Cliente $cliente): void {
                $unidade = UnidadeNegocio::query()->find($cliente->id_unidade_negocio_saida_fisico_padrao);
                $valor = $unidade?->is_hub ? 'hub' : 'galpao';
                $cliente->forceFill(['saida_estoque_fisico_padrao' => $valor])->saveQuietly();
            });

            Schema::table('clientes', function (Blueprint $table): void {
                $table->dropForeign('clientes_saida_fis_pad_un_fk');
                $table->dropColumn('id_unidade_negocio_saida_fisico_padrao');
            });
        }
    }
};
