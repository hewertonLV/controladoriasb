<?php

namespace App\Services\Captacao;

use App\Support\TextoCadastro;

final class ClienteFrutaVinculoPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $cells  [coluna A, coluna B]
     * @return array{dados: array<string, string>, erros: list<string>}
     */
    public function normalize(array $cells): array
    {
        $idCigamCliente = TextoCadastro::normalizarIdCigam((string) ($cells[0] ?? ''));
        $idCigamFruta = TextoCadastro::normalizarIdCigam((string) ($cells[1] ?? ''));

        $erros = [];

        if ($idCigamCliente === '') {
            $erros[] = 'Coluna A (ID CIGAM do cliente) é obrigatória.';
        }

        if ($idCigamFruta === '') {
            $erros[] = 'Coluna B (ID CIGAM da fruta) é obrigatória.';
        }

        return [
            'dados' => [
                'id_cigam_cliente' => $idCigamCliente,
                'id_cigam_fruta' => $idCigamFruta,
                'loja' => $idCigamCliente,
                'fruta' => $idCigamFruta,
            ],
            'erros' => $erros,
        ];
    }
}
