<?php

namespace App\Services\Frutas;

use App\Enums\FrutaUmIcms;
use App\Models\Estado;
use App\Models\Fruta;
use App\Support\TextoCadastro;

/**
 * Layout da planilha de ICMS (linha 1 = cabeçalho):
 *   A → fruta (ID CIGAM ou nome)
 *   B → estado (ID, sigla ou nome)
 *   C/D → ICMS compra nacional + UM
 *   E/F → ICMS compra exterior + UM
 *   G/H → ICMS venda importada + UM
 *   I/J → ICMS venda nacional + UM
 */
class FrutaIcmsPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{dados: array<string, mixed>, erros: list<string>}
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $frutaRef = $this->trimString($row[0] ?? null);
        $estadoRef = $this->trimString($row[1] ?? null);

        $compraNacional = $this->valorMonetario($row[2] ?? null, '0');
        $umCompraNacional = $this->valorUm($row[3] ?? null);
        $compraExterior = $this->valorMonetario($row[4] ?? null, '0');
        $umCompraExterior = $this->valorUm($row[5] ?? null);
        $vendaImportada = $this->valorMonetario($row[6] ?? null, '0');
        $umVendaImportada = $this->valorUm($row[7] ?? null);
        $vendaNacional = $this->valorMonetario($row[8] ?? null, '0');
        $umVendaNacional = $this->valorUm($row[9] ?? null);

        if ($frutaRef === '') {
            $erros[] = 'Fruta (coluna A: ID CIGAM ou nome) é obrigatória.';
        }

        if ($estadoRef === '') {
            $erros[] = 'Estado (coluna B) é obrigatório.';
        }

        foreach ([
            'UM compra nacional (D)' => $umCompraNacional,
            'UM compra exterior (F)' => $umCompraExterior,
            'UM venda importada (H)' => $umVendaImportada,
            'UM venda nacional (J)' => $umVendaNacional,
        ] as $rotulo => $um) {
            if (! in_array($um, FrutaUmIcms::values(), true)) {
                $erros[] = "{$rotulo} inválida. Use KG ou UM.";
            }
        }

        $fruta = $frutaRef !== '' ? $this->resolverFruta($frutaRef) : null;
        if ($frutaRef !== '' && $fruta === null) {
            $erros[] = "Fruta não encontrada para referência: {$frutaRef}.";
        }

        $idEstado = $estadoRef !== '' ? $this->resolverEstado($estadoRef) : null;
        if ($estadoRef !== '' && $idEstado === null) {
            $erros[] = "Estado não encontrado: {$estadoRef}.";
        }

        return [
            'dados' => [
                'fruta_id' => $fruta?->id,
                'fruta_ref' => $frutaRef,
                'fruta_nome' => $fruta?->nome,
                'fruta_id_cigam' => $fruta?->id_cigam,
                'id_estado' => $idEstado,
                'estado_ref' => $estadoRef,
                'compra_nacional' => $compraNacional,
                'um_compra_nacional' => $umCompraNacional,
                'compra_exterior' => $compraExterior,
                'um_compra_exterior' => $umCompraExterior,
                'venda_importada' => $vendaImportada,
                'um_venda_importada' => $umVendaImportada,
                'venda_nacional' => $vendaNacional,
                'um_venda_nacional' => $umVendaNacional,
            ],
            'erros' => $erros,
        ];
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

        $nome = mb_strtoupper(trim($ref), 'UTF-8');

        return Fruta::query()->where('nome', $nome)->first();
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

        $estado = Estado::query()
            ->whereRaw('UPPER(abreviacao) = ?', [$texto])
            ->orWhereRaw('UPPER(nome) = ?', [$texto])
            ->first();

        return $estado?->id;
    }

    private function trimString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
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
