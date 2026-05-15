<?php

namespace App\Services\Frutas;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → id_cigam
 *   B → nome
 *   C → unidade_medicao
 *   D → kg_por_unidade_medicao
 *   E → icms_ex_compra
 *   F → icms_na_compra
 *   G → um_icms (KG ou UM)
 *   H → icms_venda (percentual)
 */
class FrutaPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         id_cigam: string,
     *         nome: string,
     *         unidade_medicao: string,
     *         kg_por_unidade_medicao: string,
     *         icms_ex_compra: string,
     *         icms_na_compra: string,
     *         um_icms: string,
     *         icms_venda: string,
     *     },
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos(
            $this->trimString($row[0] ?? null),
        );
        $nome = $this->trimString($row[1] ?? null);
        $unidadeMedicao = mb_strtoupper($this->trimString($row[2] ?? null), 'UTF-8');
        $kg = $this->normalizeKg($row[3] ?? null);

        $icmsExRaw = $row[4] ?? null;
        $icmsNaRaw = $row[5] ?? null;
        $umIcmsRaw = $row[6] ?? null;
        $icmsVendaRaw = $row[7] ?? null;

        if ($icmsExRaw === null || trim((string) $icmsExRaw) === '') {
            $icmsExRaw = '0';
        }
        if ($icmsNaRaw === null || trim((string) $icmsNaRaw) === '') {
            $icmsNaRaw = '0';
        }
        if ($umIcmsRaw === null || trim((string) $umIcmsRaw) === '') {
            $umIcmsRaw = FrutaUmIcms::KG->value;
        }
        if ($icmsVendaRaw === null || trim((string) $icmsVendaRaw) === '') {
            $icmsVendaRaw = '0';
        }

        $icmsEx = TextoCadastro::normalizarValorMonetarioBrasileiro($icmsExRaw);
        $icmsNa = TextoCadastro::normalizarValorMonetarioBrasileiro($icmsNaRaw);
        $umIcms = TextoCadastro::normalizarMaiusculas((string) $umIcmsRaw);
        $icmsVenda = TextoCadastro::normalizarValorMonetarioBrasileiro($icmsVendaRaw);

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

        if (! in_array($umIcms, FrutaUmIcms::values(), true)) {
            $erros[] = 'Unidade ICMS (coluna G) inválida. Use KG ou UM.';
        }

        return [
            'dados' => [
                'id_cigam' => $idCigam,
                'nome' => mb_strtoupper($nome, 'UTF-8'),
                'unidade_medicao' => $unidadeMedicao,
                'kg_por_unidade_medicao' => $kg ?? '0.00',
                'icms_ex_compra' => $icmsEx,
                'icms_na_compra' => $icmsNa,
                'um_icms' => $umIcms,
                'icms_venda' => $icmsVenda,
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
            return '0.00';
        }

        if (! is_numeric((string) $value)) {
            return null;
        }

        $kg = max(0, round((float) $value, 2));

        return number_format($kg, 2, '.', '');
    }
}
