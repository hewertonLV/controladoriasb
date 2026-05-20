<?php

namespace App\Services\Frutas;

use App\Enums\FrutaUmIcms;
use App\Models\Estado;
use Illuminate\Validation\ValidationException;

class FrutaIcmsValidacaoService
{
    /**
     * @param  array<string, mixed>  $linha
     */
    public function validarLinha(int $idEstado, array $linha): void
    {
        $estado = Estado::query()->find($idEstado);
        if ($estado === null) {
            return;
        }

        $erros = [];

        foreach ([
            'entrada_um_nacional' => $linha['entrada_um_nacional'] ?? $linha['um_compra_nacional'] ?? FrutaUmIcms::KG->value,
            'entrada_um_externo' => $linha['entrada_um_externo'] ?? $linha['um_compra_exterior'] ?? FrutaUmIcms::KG->value,
        ] as $campo => $um) {
            if (! in_array(mb_strtoupper((string) $um, 'UTF-8'), FrutaUmIcms::valoresEntrada(), true)) {
                $erros[$campo][] = 'Na entrada use apenas KG ou UM.';
            }
        }

        if ($estado->cobraIcmsNaSaida()) {
            foreach ([
                'saida_um_importada' => $linha['saida_um_importada'] ?? $linha['um_venda_importada'] ?? FrutaUmIcms::PCT->value,
                'saida_um_nacional' => $linha['saida_um_nacional'] ?? $linha['um_venda_nacional'] ?? FrutaUmIcms::PCT->value,
            ] as $campo => $um) {
                $umNorm = mb_strtoupper((string) $um, 'UTF-8');
                if ($umNorm !== FrutaUmIcms::PCT->value
                    && ((float) ($linha['saida_importada'] ?? $linha['venda_importada'] ?? 0) > 0
                        || (float) ($linha['saida_nacional'] ?? $linha['venda_nacional'] ?? 0) > 0)) {
                    $erros[$campo][] = 'Em Pernambuco, alíquotas de venda devem usar UM PCT (%).';
                }
            }
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>
     */
    public function normalizarLinha(int $idEstado, array $linha): array
    {
        $estado = Estado::query()->find($idEstado);

        if ($estado?->cobraIcmsNaSaida()) {
            if ((float) ($linha['saida_importada'] ?? $linha['venda_importada'] ?? 0) > 0) {
                $linha['saida_um_importada'] = FrutaUmIcms::PCT->value;
                $linha['um_venda_importada'] = FrutaUmIcms::PCT->value;
            }
            if ((float) ($linha['saida_nacional'] ?? $linha['venda_nacional'] ?? 0) > 0) {
                $linha['saida_um_nacional'] = FrutaUmIcms::PCT->value;
                $linha['um_venda_nacional'] = FrutaUmIcms::PCT->value;
            }
        }

        foreach (['entrada_um_nacional', 'entrada_um_externo'] as $campo) {
            if (isset($linha[$campo])) {
                $linha[$campo] = mb_strtoupper((string) $linha[$campo], 'UTF-8');
            }
        }

        return $linha;
    }
}
