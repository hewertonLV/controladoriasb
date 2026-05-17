<?php

namespace App\Services\Pracas;

use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → nome
 *   B → id_cigam da unidade de negócio
 */
class PracaPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         nome: string,
     *         id_unidade_negocio: int,
     *         id_cigam_unidade: string,
     *     },
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $nome = TextoCadastro::normalizarMaiusculas($this->trimString($row[0] ?? null));
        $unidadeOriginal = $this->trimString($row[1] ?? null);
        $idCigamUnidade = TextoCadastro::normalizarIdCigam($unidadeOriginal);
        $idUnidade = 0;

        if ($nome === '') {
            $erros[] = 'Nome (coluna A) é obrigatório.';
        } elseif (mb_strlen($nome) > 255) {
            $erros[] = 'Nome pode ter no máximo 255 caracteres.';
        }

        if ($idCigamUnidade === '') {
            $erros[] = 'ID CIGAM da unidade de negócio (coluna B) é obrigatório.';
        } elseif (! preg_match('/^\d{6}$/', $idCigamUnidade)) {
            $erros[] = 'ID CIGAM da unidade de negócio (coluna B) deve ter até 6 dígitos numéricos.';
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

        return [
            'dados' => [
                'nome' => $nome,
                'id_unidade_negocio' => $idUnidade,
                'id_cigam_unidade' => $idCigamUnidade,
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
