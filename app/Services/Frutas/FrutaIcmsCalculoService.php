<?php

namespace App\Services\Frutas;

use App\Enums\FrutaIcmsOperacao;
use App\Models\Cliente;
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

    /**
     * ICMS na venda (ex.: Pernambuco): percentual sobre o valor da NF.
     *
     * @return array{
     *     valor_icms_total: string,
     *     valor_icms_kg: string,
     *     valor_icms_um: string,
     *     icms_convertido_kg: string,
     * }
     */
    public function calcularSaidaSobreValorVenda(
        Fruta $fruta,
        UnidadeNegocio $unidadeFaturamento,
        Cliente $cliente,
        string $valorVendaTotal,
        float $qtdKg,
        float $qtdUm,
        ?DateTimeInterface $dataReferencia = null,
    ): array {
        $zerado = [
            'valor_icms_total' => '0.00',
            'valor_icms_kg' => '0.00',
            'valor_icms_um' => '0.00',
            'icms_convertido_kg' => '0.00',
        ];

        $unidadeFaturamento->loadMissing('estado');
        if (! $this->deveAplicarIcmsSaida($unidadeFaturamento)) {
            return $zerado;
        }

        $config = $this->resolverConfigSaida($fruta, (int) $unidadeFaturamento->id_estado, $dataReferencia);
        if ($config === null) {
            return $zerado;
        }

        $valorVenda = max(0, (float) $valorVendaTotal);
        if ($valorVenda <= 0) {
            return $zerado;
        }

        $dentroDoEstado = $this->vendaDentroDoEstadoFaturamento($unidadeFaturamento, $cliente);
        $total = (float) $config->calcularIcmsSaidaSobreValor($valorVenda, $dentroDoEstado);

        return [
            'valor_icms_total' => number_format($total, 2, '.', ''),
            'valor_icms_kg' => number_format($qtdKg > 0 ? round($total / $qtdKg, 2) : 0, 2, '.', ''),
            'valor_icms_um' => number_format($qtdUm > 0 ? round($total / $qtdUm, 2) : 0, 2, '.', ''),
            'icms_convertido_kg' => number_format($qtdKg > 0 ? round($total / $qtdKg, 2) : 0, 2, '.', ''),
        ];
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

    private function resolverConfigSaida(
        Fruta $fruta,
        int $idEstado,
        ?DateTimeInterface $dataReferencia,
    ): ?FrutaIcms {
        if ($dataReferencia !== null) {
            $historico = $this->historicoService->vigenteNaData($fruta->id, $idEstado, $dataReferencia);

            if ($historico !== null) {
                return $historico->comoConfigSaida();
            }
        }

        return FrutaIcms::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', $idEstado)
            ->where('operacao', FrutaIcmsOperacao::SAIDA)
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

    private function deveAplicarIcmsSaida(UnidadeNegocio $unidadeFaturamento): bool
    {
        return $unidadeFaturamento->estado?->cobraIcmsNaSaida() ?? false;
    }

    private function vendaDentroDoEstadoFaturamento(UnidadeNegocio $unidadeFaturamento, Cliente $cliente): bool
    {
        $cliente->loadMissing('unidadeNegocio.estado');
        $idEstadoFaturamento = (int) $unidadeFaturamento->id_estado;
        $idEstadoCliente = (int) ($cliente->unidadeNegocio?->id_estado ?? 0);

        return $idEstadoCliente > 0 && $idEstadoCliente === $idEstadoFaturamento;
    }
}
