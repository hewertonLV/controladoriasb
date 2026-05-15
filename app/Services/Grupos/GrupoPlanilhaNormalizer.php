<?php

namespace App\Services\Grupos;

use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → nome
 */
class GrupoPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{nome: string},
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $nomeBruto = $this->trimString($row[0] ?? null);
        $nome = TextoCadastro::normalizarMaiusculas($nomeBruto);

        if ($nome === '') {
            $erros[] = 'Nome (coluna A) é obrigatório.';
        }

        return [
            'dados' => [
                'nome' => $nome,
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
