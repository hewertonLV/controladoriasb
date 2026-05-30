<?php

namespace App\Services\Captacao;

use App\Support\TextoCadastro;

final class CaptacaoCarteiraImportacaoPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $cells
     * @return array{dados: array<string, string>, erros: list<string>}
     */
    public function normalize(array $cells): array
    {
        $idCigamCliente = TextoCadastro::normalizarIdCigam((string) ($cells[0] ?? ''));

        $erros = [];

        if ($idCigamCliente === '') {
            $erros[] = 'Coluna A (ID CIGAM do cliente) é obrigatória.';
        }

        return [
            'dados' => [
                'id_cigam_cliente' => $idCigamCliente,
                'codigo' => $idCigamCliente,
            ],
            'erros' => $erros,
        ];
    }
}
