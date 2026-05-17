<?php

namespace App\Services\Veiculos;

use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → id_sbs
 *   B → nome
 *   C → tipo
 *   D → id_cigam da unidade de negócio
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
     *         id_cigam_unidade: string,
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
        $unidadeOriginal = $this->trimString($row[3] ?? null);
        $idCigamUnidade = TextoCadastro::normalizarIdCigam($unidadeOriginal);
        $status = TextoCadastro::normalizarStatusAtivoInativo($this->trimString($row[4] ?? null) ?: 'ATIVO');

        $idSbs = $idSbsRaw !== '' ? (int) $idSbsRaw : 0;
        $idUnidade = 0;

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

        if ($idCigamUnidade === '') {
            $erros[] = 'ID CIGAM da unidade de negócio (coluna D) é obrigatório.';
        } elseif (! preg_match('/^\d{6}$/', $idCigamUnidade)) {
            $erros[] = 'ID CIGAM da unidade de negócio (coluna D) deve ter até 6 dígitos numéricos.';
        } else {
            $unidade = UnidadeNegocio::query()
                ->where('id_cigam', $idCigamUnidade)
                ->first(['id']);

            if ($unidade === null) {
                $erros[] = $this->mensagemUnidadeNaoEncontrada($idCigamUnidade, $unidadeOriginal);
            } else {
                $idUnidade = (int) $unidade->id;
            }
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
                'id_cigam_unidade' => $idCigamUnidade,
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

    private function mensagemUnidadeNaoEncontrada(string $normalizado, string $original): string
    {
        return "Unidade de negócio com id_cigam {$normalizado} não encontrada. Valor original informado: {$original}.";
    }
}
