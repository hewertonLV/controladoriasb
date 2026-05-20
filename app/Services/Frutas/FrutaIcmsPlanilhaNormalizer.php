<?php

namespace App\Services\Frutas;

use App\Enums\FrutaUmIcms;
use App\Models\Estado;
use App\Models\Fruta;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use App\Support\TextoCadastro;

/**
 * Layout preferencial (A–H, ADR-0027):
 *   C entrada nacional (R$/kg)
 *   D entrada internacional (R$/kg)
 *   E venda nacional dentro (%)
 *   F venda nacional fora (%)
 *   G venda internacional dentro (%)
 *   H venda internacional fora (%)
 *
 * Layout legado (A–J + K opcional, ADR-0014/0026): converte UM de compra para R$/kg quando possível.
 */
class FrutaIcmsPlanilhaNormalizer
{
    private const TIPO_PCT = 'PCT';

    private const TIPO_REAL = 'REAL';

    /**
     * @param  list<mixed>  $row
     * @return array{dados: array<string, mixed>, erros: list<string>}
     */
    public function normalize(array $row): array
    {
        $erros = [];
        $frutaRef = $this->trimString($row[0] ?? null);
        $estadoRef = $this->trimString($row[1] ?? null);

        if ($frutaRef === '') {
            $erros[] = 'Fruta (coluna A: ID CIGAM ou nome) é obrigatória.';
        }

        if ($estadoRef === '') {
            $erros[] = 'Estado (coluna B) é obrigatório.';
        }

        $fruta = $frutaRef !== '' ? $this->resolverFruta($frutaRef) : null;
        if ($frutaRef !== '' && $fruta === null) {
            $erros[] = "Fruta não encontrada para referência: {$frutaRef}.";
        }

        $idEstado = $estadoRef !== '' ? $this->resolverEstado($estadoRef) : null;
        if ($estadoRef !== '' && $idEstado === null) {
            $erros[] = "Estado não encontrado: {$estadoRef}.";
        }

        $legado = $this->ehLayoutLegadoComUm($row);
        $linha = $legado ? $this->layoutLegado($row) : $this->layoutNovo($row);

        if ($legado) {
            $erros = array_merge($erros, $this->validarLayoutLegado($linha, $row));
            $linha = $this->aplicarColunaK($linha, $row);
        }

        $aliases = $legado ? $this->aliasesLegado($linha) : [];
        $linha = $this->converterEntradaUmParaKg($linha, $fruta);

        $dados = array_merge([
            'fruta_id' => $fruta?->id,
            'fruta_ref' => $frutaRef,
            'fruta_nome' => $fruta?->nome,
            'fruta_id_cigam' => $fruta?->id_cigam,
            'id_estado' => $idEstado,
            'estado_ref' => $estadoRef,
        ], $linha, $aliases);

        return ['dados' => $dados, 'erros' => $erros];
    }

    /**
     * @param  list<mixed>  $row
     */
    private function ehLayoutLegadoComUm(array $row): bool
    {
        foreach ([3, 5, 7, 9] as $indiceUm) {
            $valor = mb_strtoupper(trim((string) ($row[$indiceUm] ?? '')), 'UTF-8');
            if ($valor !== '' && in_array($valor, FrutaUmIcms::values(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $row
     * @return array<string, string>
     */
    private function layoutNovo(array $row): array
    {
        return [
            FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => $this->valorMonetario($row[2] ?? null, '0'),
            FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => $this->valorMonetario($row[3] ?? null, '0'),
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => $this->valorMonetario($row[4] ?? null, '0'),
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => $this->valorMonetario($row[5] ?? null, '0'),
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => $this->valorMonetario($row[6] ?? null, '0'),
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => $this->valorMonetario($row[7] ?? null, '0'),
        ];
    }

    /**
     * @param  list<mixed>  $row
     * @return array<string, string>
     */
    private function layoutLegado(array $row): array
    {
        $colG = $this->valorMonetario($row[6] ?? null, '0');
        $colI = $this->valorMonetario($row[8] ?? null, '0');

        return [
            FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => $this->valorMonetario($row[2] ?? null, '0'),
            FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => $this->valorMonetario($row[4] ?? null, '0'),
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => $colG,
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => $colI,
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => $colG,
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => $colI,
            '_um_compra_nacional' => $this->valorUm($row[3] ?? null),
            '_um_compra_exterior' => $this->valorUm($row[5] ?? null),
            '_um_venda_fora' => $this->valorUm($row[7] ?? null),
            '_um_venda_dentro' => $this->valorUm($row[9] ?? null),
        ];
    }

    /**
     * @param  array<string, string>  $linha
     * @param  list<mixed>  $row
     * @return list<string>
     */
    private function validarLayoutLegado(array $linha, array $row): array
    {
        $erros = [];

        foreach ([
            'UM compra nacional (D)' => $linha['_um_compra_nacional'] ?? FrutaUmIcms::KG->value,
            'UM compra exterior (F)' => $linha['_um_compra_exterior'] ?? FrutaUmIcms::KG->value,
        ] as $rotulo => $um) {
            if (! in_array($um, FrutaUmIcms::valoresEntrada(), true)) {
                $erros[] = "{$rotulo} inválida. Use KG ou UM.";
            }
        }

        foreach ([
            'UM venda fora do estado (H)' => $linha['_um_venda_fora'] ?? FrutaUmIcms::KG->value,
            'UM venda dentro do estado (J)' => $linha['_um_venda_dentro'] ?? FrutaUmIcms::KG->value,
        ] as $rotulo => $um) {
            if (! in_array($um, FrutaUmIcms::valoresSaida(), true)) {
                $erros[] = "{$rotulo} inválida. Use KG, UM ou PCT.";
            }
        }

        if (array_key_exists(10, $row)) {
            $tipoK = mb_strtoupper(trim((string) $row[10]), 'UTF-8');
            if ($tipoK !== '' && ! in_array($tipoK, [self::TIPO_PCT, self::TIPO_REAL], true)) {
                $erros[] = 'coluna K (tipo de estado) inválida. Use REAL ou PCT.';
            }
        }

        return $erros;
    }

    /**
     * @param  array<string, string>  $linha
     * @param  list<mixed>  $row
     * @return array<string, string>
     */
    private function aplicarColunaK(array $linha, array $row): array
    {
        if (! array_key_exists(10, $row)) {
            return $linha;
        }

        $tipoK = mb_strtoupper(trim((string) $row[10]), 'UTF-8');

        if ($tipoK === self::TIPO_PCT) {
            $linha['_um_venda_fora'] = FrutaUmIcms::PCT->value;
            $linha['_um_venda_dentro'] = FrutaUmIcms::PCT->value;
        }

        return $linha;
    }

    /**
     * @param  array<string, string>  $linha
     * @return array<string, string>
     */
    private function aliasesLegado(array $linha): array
    {
        return [
            'compra_nacional' => $linha[FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG] ?? '0.00',
            'um_compra_nacional' => $linha['_um_compra_nacional'] ?? FrutaUmIcms::KG->value,
            'compra_exterior' => $linha[FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG] ?? '0.00',
            'um_compra_exterior' => $linha['_um_compra_exterior'] ?? FrutaUmIcms::KG->value,
            'venda_nacional' => $linha[FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT] ?? '0.00',
            'um_venda_nacional' => $linha['_um_venda_dentro'] ?? FrutaUmIcms::KG->value,
            'venda_importada' => $linha[FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT] ?? '0.00',
            'um_venda_importada' => $linha['_um_venda_fora'] ?? FrutaUmIcms::KG->value,
        ];
    }

    /**
     * @param  array<string, string>  $linha
     * @return array<string, string>
     */
    private function converterEntradaUmParaKg(array $linha, ?Fruta $fruta): array
    {
        if ($fruta === null) {
            unset(
                $linha['_um_compra_nacional'],
                $linha['_um_compra_exterior'],
                $linha['_um_venda_fora'],
                $linha['_um_venda_dentro'],
            );

            return $linha;
        }

        $kgPorUm = max(0.0001, (float) $fruta->kg_por_unidade_medicao);

        foreach ([
            FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '_um_compra_nacional',
            FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '_um_compra_exterior',
        ] as $chaveValor => $chaveUm) {
            $um = mb_strtoupper(trim((string) ($linha[$chaveUm] ?? FrutaUmIcms::KG->value)), 'UTF-8');
            $valor = (float) ($linha[$chaveValor] ?? 0);

            if ($um === FrutaUmIcms::UM->value) {
                $linha[$chaveValor] = number_format(round($valor / $kgPorUm, 4), 2, '.', '');
            }

            unset($linha[$chaveUm]);
        }

        unset($linha['_um_venda_fora'], $linha['_um_venda_dentro']);

        return $linha;
    }

    private function resolverFruta(string $ref): ?Fruta
    {
        $digits = preg_replace('/\D/', '', $ref) ?? '';
        if ($digits !== '') {
            $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos($digits);
            $porCigam = Fruta::query()->where('id_cigam', $idCigam)->first();
            if ($porCigam !== null) {
                return $porCigam;
            }
        }

        return Fruta::query()->where('nome', mb_strtoupper(trim($ref), 'UTF-8'))->first();
    }

    private function resolverEstado(string $ref): ?int
    {
        if (ctype_digit($ref)) {
            $id = (int) $ref;
            if (Estado::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $texto = mb_strtoupper(TextoCadastro::removerAcentos($ref), 'UTF-8');

        return Estado::query()
            ->whereRaw('UPPER(abreviacao) = ?', [$texto])
            ->orWhereRaw('UPPER(nome) = ?', [$texto])
            ->first()?->id;
    }

    private function trimString(mixed $value): string
    {
        return $value === null ? '' : trim((string) $value);
    }

    private function valorMonetario(mixed $value, string $padrao): string
    {
        if ($value === null || trim((string) $value) === '') {
            return TextoCadastro::normalizarValorMonetarioBrasileiro($padrao);
        }

        return TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    private function valorUm(mixed $value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return FrutaUmIcms::KG->value;
        }

        return TextoCadastro::normalizarMaiusculas((string) $value);
    }
}
