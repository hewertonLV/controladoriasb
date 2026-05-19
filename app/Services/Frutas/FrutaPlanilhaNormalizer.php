<?php

namespace App\Services\Frutas;

use App\Enums\FrutaUnidadeMedicao;
use App\Support\TextoCadastro;

/**
 * Layout da planilha de frutas (linha 1 = cabeçalho):
 *   A → id_cigam
 *   B → nome
 *   C → unidade_medicao
 *   D → kg_por_unidade_medicao
 */
class FrutaPlanilhaNormalizer
{
    private const UNIDADE_MEDICAO_ALIASES = [
        'CAIXA' => FrutaUnidadeMedicao::CAIXA->value,
        'CX' => FrutaUnidadeMedicao::CAIXA->value,
        'PACOTE' => FrutaUnidadeMedicao::PACOTE->value,
        'PCT' => FrutaUnidadeMedicao::PACOTE->value,
        'PC' => FrutaUnidadeMedicao::PACOTE->value,
        'UNIDADE' => FrutaUnidadeMedicao::UNIDADE->value,
        'UN' => FrutaUnidadeMedicao::UNIDADE->value,
        'UND' => FrutaUnidadeMedicao::UNIDADE->value,
        'SACO' => FrutaUnidadeMedicao::SACO->value,
        'SC' => FrutaUnidadeMedicao::SACO->value,
    ];

    /**
     * @param  list<mixed>  $row
     * @return array{dados: array<string, string>, erros: list<string>}
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos(
            $this->trimString($row[0] ?? null),
        );
        $nome = $this->trimString($row[1] ?? null);
        $unidadeMedicao = self::normalizarUnidadeMedicao($row[2] ?? null);
        $kg = $this->normalizeKg($row[3] ?? null);

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM (coluna A) é obrigatório.';
        } elseif (strlen($idCigam) > 6) {
            $erros[] = 'ID CIGAM deve ter no máximo 6 dígitos numéricos.';
        }

        if ($nome === '') {
            $erros[] = 'Nome (coluna B) é obrigatório.';
        }

        if ($unidadeMedicao === '') {
            $erros[] = 'Unidade de medição (coluna C) é obrigatória.';
        } elseif (! in_array($unidadeMedicao, FrutaUnidadeMedicao::values(), true)) {
            $erros[] = 'Unidade de medição inválida. Valores permitidos: '.implode(', ', FrutaUnidadeMedicao::values()).'.';
        }

        if ($kg === null) {
            $erros[] = 'Kg por unidade de medição (coluna D) é obrigatório e deve ser numérico (mínimo 0).';
        }

        return [
            'dados' => [
                'id_cigam' => $idCigam,
                'nome' => mb_strtoupper($nome, 'UTF-8'),
                'unidade_medicao' => $unidadeMedicao,
                'kg_por_unidade_medicao' => $kg ?? '0.00',
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

    private function normalizeKg(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalizado = TextoCadastro::normalizarValorMonetarioBrasileiro($value);

        if (! is_numeric($normalizado)) {
            return null;
        }

        return number_format(max(0, (float) $normalizado), 2, '.', '');
    }

    public static function normalizarUnidadeMedicao(mixed $value): string
    {
        $texto = mb_strtoupper(trim((string) ($value ?? '')), 'UTF-8');

        return self::UNIDADE_MEDICAO_ALIASES[$texto] ?? $texto;
    }
}
