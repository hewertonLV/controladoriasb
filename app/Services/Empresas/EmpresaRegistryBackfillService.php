<?php

namespace App\Services\Empresas;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaHistorico;
use App\Models\Fornecedor;
use App\Models\UnidadeNegocio;
use Illuminate\Support\Facades\DB;

/**
 * Cria linhas em `empresas` para cada cliente, fornecedor e unidade de negócio,
 * sem duplicar dados cadastrais (apenas o vínculo morph).
 */
final class EmpresaRegistryBackfillService
{
    public function executar(bool $registrarHistorico = false): int
    {
        $inseridos = 0;

        DB::transaction(function () use ($registrarHistorico, &$inseridos): void {
            $pairs = [
                [Cliente::class, Cliente::query()->pluck('id')],
                [Fornecedor::class, Fornecedor::query()->pluck('id')],
                [UnidadeNegocio::class, UnidadeNegocio::query()->pluck('id')],
            ];

            foreach ($pairs as [$class, $ids]) {
                foreach ($ids as $id) {
                    $exists = Empresa::query()
                        ->where('entidade_type', $class)
                        ->where('entidade_id', $id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $empresa = Empresa::query()->create([
                        'entidade_type' => $class,
                        'entidade_id' => $id,
                    ]);
                    $inseridos++;

                    if ($registrarHistorico) {
                        $empresa->load('entidade');
                        app(EmpresaAuditoriaService::class)->registrarCriacao(
                            $empresa,
                            null,
                            EmpresaHistorico::ORIGEM_SISTEMA,
                        );
                    }
                }
            }
        });

        return $inseridos;
    }
}
