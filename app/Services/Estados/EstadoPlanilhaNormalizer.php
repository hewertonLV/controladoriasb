<?php

namespace App\Services\Estados;

use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → id_cigam
 *   B → nome
 *   C → abreviacao (sigla UF)
 *   D → descricao (opcional)
 */
class EstadoPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{id_cigam: string, nome: string, abreviacao: string, descricao: string|null},
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos(
            $this->trimString($row[0] ?? null),
        );
        $nome = TextoCadastro::normalizarMaiusculas($this->trimString($row[1] ?? null));
        $abreviacao = TextoCadastro::normalizarMaiusculas($this->trimString($row[2] ?? null));
        $descricaoBruta = $this->trimString($row[3] ?? null);
        $descricao = $descricaoBruta === '' ? null : $descricaoBruta;

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM (coluna A) é obrigatório.';
        } elseif (strlen($idCigam) > 6) {
            $erros[] = 'ID CIGAM deve ter no máximo 6 dígitos numéricos.';
        }

        if ($nome === '') {
            $erros[] = 'Nome (coluna B) é obrigatório.';
        }

        if ($abreviacao === '') {
            $erros[] = 'Abreviação (coluna C) é obrigatória.';
        } elseif (mb_strlen($abreviacao, 'UTF-8') !== 2) {
            $erros[] = 'Abreviação (coluna C) deve ter exatamente 2 caracteres.';
        }

        return [
            'dados' => [
                'id_cigam' => $idCigam,
                'nome' => $nome,
                'abreviacao' => $abreviacao,
                'descricao' => $descricao,
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
