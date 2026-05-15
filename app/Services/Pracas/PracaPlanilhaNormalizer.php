<?php

namespace App\Services\Pracas;

use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → nome
 *   B → id_unidade_negocio
 */
class PracaPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         nome: string,
     *         id_unidade_negocio: int,
     *     },
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $nome = TextoCadastro::normalizarMaiusculas($this->trimString($row[0] ?? null));
        $unidadeRaw = TextoCadastro::somenteDigitos($this->trimString($row[1] ?? null));
        $idUnidade = $unidadeRaw !== '' ? (int) $unidadeRaw : 0;

        if ($nome === '') {
            $erros[] = 'Nome (coluna A) é obrigatório.';
        } elseif (mb_strlen($nome) > 255) {
            $erros[] = 'Nome pode ter no máximo 255 caracteres.';
        }

        if ($idUnidade <= 0) {
            $erros[] = 'ID Unidade de Negócio (coluna B) deve ser um inteiro positivo.';
        } elseif (! UnidadeNegocio::query()->whereKey($idUnidade)->exists()) {
            $erros[] = "Unidade de negócio #{$idUnidade} não encontrada.";
        }

        return [
            'dados' => [
                'nome' => $nome,
                'id_unidade_negocio' => $idUnidade,
            ],
            'erros' => $erros,
        ];
    }

    private function trimString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
