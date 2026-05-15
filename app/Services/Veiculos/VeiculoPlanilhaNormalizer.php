<?php

namespace App\Services\Veiculos;

use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → id_sbs
 *   B → nome
 *   C → tipo
 *   D → id_unidade_negocio
 *   E → status (ATIVO|INATIVO)
 */
class VeiculoPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         id_sbs: int,
     *         nome: string,
     *         tipo: string,
     *         id_unidade_negocio: int,
     *         status: string,
     *     },
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $idSbsRaw = TextoCadastro::somenteDigitos($this->trimString($row[0] ?? null));
        $nome = TextoCadastro::normalizarMaiusculas($this->trimString($row[1] ?? null));
        $tipo = TextoCadastro::normalizarMaiusculas($this->trimString($row[2] ?? null));
        $unidadeRaw = TextoCadastro::somenteDigitos($this->trimString($row[3] ?? null));
        $status = TextoCadastro::normalizarStatusAtivoInativo($this->trimString($row[4] ?? null) ?: 'ATIVO');

        $idSbs = $idSbsRaw !== '' ? (int) $idSbsRaw : 0;
        $idUnidade = $unidadeRaw !== '' ? (int) $unidadeRaw : 0;

        if ($idSbs <= 0) {
            $erros[] = 'ID SBS (coluna A) deve ser um inteiro positivo.';
        }

        if ($nome === '') {
            $erros[] = 'Nome (coluna B) é obrigatório.';
        } elseif (mb_strlen($nome) > 255) {
            $erros[] = 'Nome pode ter no máximo 255 caracteres.';
        }

        if ($tipo === '') {
            $erros[] = 'Tipo (coluna C) é obrigatório.';
        } elseif (mb_strlen($tipo) > 255) {
            $erros[] = 'Tipo pode ter no máximo 255 caracteres.';
        }

        if ($idUnidade <= 0) {
            $erros[] = 'ID Unidade de Negócio (coluna D) deve ser um inteiro positivo.';
        } elseif (! UnidadeNegocio::query()->whereKey($idUnidade)->exists()) {
            $erros[] = "Unidade de negócio #{$idUnidade} não encontrada.";
        }

        $statusUpper = mb_strtoupper($status);
        if (! in_array($statusUpper, ['ATIVO', 'INATIVO'], true)) {
            $erros[] = 'Status (coluna E) deve ser ATIVO ou INATIVO.';
        }

        return [
            'dados' => [
                'id_sbs' => $idSbs,
                'nome' => $nome,
                'tipo' => $tipo,
                'id_unidade_negocio' => $idUnidade,
                'status' => $statusUpper,
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
