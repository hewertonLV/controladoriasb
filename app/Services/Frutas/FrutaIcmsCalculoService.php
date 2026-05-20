<?php

namespace App\Services\Frutas;

use App\Enums\EstadoIcmsCobraEm;
use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaProcedencia;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use DateTimeInterface;

class FrutaIcmsCalculoService
{
    public function __construct(
        private readonly FrutaIcmsAliquotaResolver $resolver,
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

        $procedencia = $fruta->procedenciaEnum();
        $config = $this->resolver->buscarEntradaPorKg(
            $fruta,
            (int) $unidadeDestino->id_estado,
            $procedencia,
            $dataReferencia,
        );

        if ($config === null) {
            return '0.00';
        }

        return number_format($config->valorPorKgEntrada(), 2, '.', '');
    }

    /**
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
        if ($unidadeFaturamento->estado?->icms_cobra_em !== EstadoIcmsCobraEm::SAIDA->value) {
            return $zerado;
        }

        $valorVenda = max(0, (float) $valorVendaTotal);
        if ($valorVenda <= 0) {
            return $zerado;
        }

        $escopo = $this->escopoVenda($unidadeFaturamento, $cliente);
        $config = $this->resolver->buscarSaidaPercentual(
            $fruta,
            (int) $unidadeFaturamento->id_estado,
            $fruta->procedenciaEnum(),
            $escopo,
            $dataReferencia,
        );

        if ($config === null) {
            return $zerado;
        }

        $total = (float) $config->calcularIcmsSaidaSobreValor($valorVenda);

        return [
            'valor_icms_total' => number_format($total, 2, '.', ''),
            'valor_icms_kg' => number_format($qtdKg > 0 ? round($total / $qtdKg, 2) : 0, 2, '.', ''),
            'valor_icms_um' => number_format($qtdUm > 0 ? round($total / $qtdUm, 2) : 0, 2, '.', ''),
            'icms_convertido_kg' => number_format($qtdKg > 0 ? round($total / $qtdKg, 2) : 0, 2, '.', ''),
        ];
    }

    private function escopoVenda(UnidadeNegocio $unidadeFaturamento, Cliente $cliente): FrutaIcmsEscopoVenda
    {
        $cliente->loadMissing('unidadeNegocio');
        $idEstadoFaturamento = (int) $unidadeFaturamento->id_estado;
        $idEstadoCliente = (int) ($cliente->unidadeNegocio?->id_estado ?? 0);

        if ($idEstadoCliente > 0 && $idEstadoCliente === $idEstadoFaturamento) {
            return FrutaIcmsEscopoVenda::DENTRO_ESTADO;
        }

        return FrutaIcmsEscopoVenda::FORA_ESTADO;
    }

    private function deveAplicarIcmsEntrada(
        UnidadeNegocio $unidadeDestino,
        ?Fornecedor $fornecedor,
        ?UnidadeNegocio $unidadeOrigem,
    ): bool {
        $unidadeDestino->loadMissing('estado');

        if ($unidadeDestino->estado?->icms_cobra_em !== EstadoIcmsCobraEm::ENTRADA->value) {
            return false;
        }

        if ($unidadeOrigem !== null) {
            $unidadeOrigem->loadMissing('estado');
            $nomeOrigem = $unidadeOrigem->estado?->nome ?? '';
            $nomeDestino = $unidadeDestino->estado?->nome ?? '';

            return $nomeOrigem !== 'CEARA' && $nomeDestino === 'CEARA';
        }

        if ($fornecedor !== null) {
            $fornecedor->loadMissing('estado');
            $nomeFornecedor = $fornecedor->estado?->nome ?? '';
            $nomeDestino = $unidadeDestino->estado?->nome ?? '';

            return $nomeDestino === 'CEARA' && $nomeFornecedor !== 'CEARA';
        }

        return false;
    }
}
