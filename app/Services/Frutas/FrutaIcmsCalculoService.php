<?php

namespace App\Services\Frutas;

use App\Enums\FrutaIcmsOperacao;
use App\Models\Fornecedor;
use App\Models\Fruta;
use App\Models\FrutaIcms;
use App\Models\UnidadeNegocio;
use DateTimeInterface;

class FrutaIcmsCalculoService
{
    public function __construct(
        private readonly FrutaIcmsHistoricoService $historicoService,
    ) {}

    public function calcularEntradaPorKg(
        Fruta $fruta,
        UnidadeNegocio $unidadeDestino,
        ?Fornecedor $fornecedor = null,
        ?UnidadeNegocio $unidadeOrigem = null,
        ?DateTimeInterface $dataReferencia = null,
    ): string {
        $unidadeDestino->loadMissing('estado');

        if (! $this->deveAplicarIcmsEntrada($unidadeDestino, $fornecedor, $unidadeOrigem)) {
            return '0.00';
        }

        $config = $this->resolverConfigEntrada($fruta, (int) $unidadeDestino->id_estado, $dataReferencia);

        if ($config === null) {
            return '0.00';
        }

        return $config->converterParaIcmsPorKg($fruta->kg_por_unidade_medicao);
    }

    private function resolverConfigEntrada(
        Fruta $fruta,
        int $idEstado,
        ?DateTimeInterface $dataReferencia,
    ): ?FrutaIcms {
        if ($dataReferencia !== null) {
            $historico = $this->historicoService->vigenteNaData($fruta->id, $idEstado, $dataReferencia);

            if ($historico !== null) {
                return $historico->comoConfigEntrada();
            }
        }

        return FrutaIcms::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->where('operacao', FrutaIcmsOperacao::ENTRADA)
            ->first();
    }

    private function deveAplicarIcmsEntrada(
        UnidadeNegocio $unidadeDestino,
        ?Fornecedor $fornecedor,
        ?UnidadeNegocio $unidadeOrigem,
    ): bool {
        $unidadeDestino->loadMissing('estado');
        $nomeDestino = $unidadeDestino->estado?->nome ?? '';

        if ($unidadeOrigem !== null) {
            $unidadeOrigem->loadMissing('estado');
            $nomeOrigem = $unidadeOrigem->estado?->nome ?? '';

            return $nomeOrigem !== 'CEARA' && $nomeDestino === 'CEARA';
        }

        if ($fornecedor !== null) {
            $fornecedor->loadMissing('estado');
            $nomeFornecedor = $fornecedor->estado?->nome ?? '';

            return $nomeDestino === 'CEARA' && $nomeFornecedor !== 'CEARA';
        }

        return false;
    }
}
