<?php

namespace App\Services\Fretes;

use App\Enums\FreteStatusSituacao;
use App\Models\Veiculo;
use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → nome
 *   B → valor
 *   C → id_veiculo (ID SBS do veículo)
 *   D → descricao
 *   E → status_situacao (ABERTA|ENCERRADA)
 *   F → valor_fruta_kg
 */
class FretePlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         nome: string,
     *         valor: string,
     *         id_veiculo: int,
     *         id_sbs: int,
     *         descricao: string|null,
     *         status_situacao: string,
     *         valor_fruta_kg: string,
     *     },
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $nome = TextoCadastro::normalizarMaiusculas($this->trimString($row[0] ?? null));
        $valor = TextoCadastro::normalizarDecimalNaoNegativo($row[1] ?? null);
        $idSbsRaw = TextoCadastro::somenteDigitos($this->trimString($row[2] ?? null));
        $descricao = $this->trimString($row[3] ?? null);
        $status = mb_strtoupper($this->trimString($row[4] ?? null) ?: 'ABERTA', 'UTF-8');
        $valorFrutaKg = TextoCadastro::normalizarDecimalNaoNegativo($row[5] ?? null);

        if ($nome === '') {
            $erros[] = 'Nome (coluna A) é obrigatório.';
        } elseif (mb_strlen($nome) > 255) {
            $erros[] = 'Nome pode ter no máximo 255 caracteres.';
        }

        $idSbs = $idSbsRaw !== '' ? (int) $idSbsRaw : 0;
        $idVeiculo = 0;

        if ($idSbs <= 0) {
            $erros[] = 'ID SBS do veículo (coluna C) deve ser um inteiro positivo.';
        } else {
            $veiculo = Veiculo::query()->where('id_sbs', $idSbs)->first();
            if ($veiculo === null) {
                $erros[] = "Veículo com ID SBS {$idSbs} não encontrado.";
            } else {
                $idVeiculo = (int) $veiculo->id;
            }
        }

        if (! in_array($status, FreteStatusSituacao::values(), true)) {
            $erros[] = 'Status situação (coluna E) deve ser ABERTA ou ENCERRADA.';
        }

        return [
            'dados' => [
                'nome' => $nome,
                'valor' => $valor,
                'id_veiculo' => $idVeiculo,
                'id_sbs' => $idSbs,
                'descricao' => $descricao !== '' ? $descricao : null,
                'status_situacao' => $status,
                'valor_fruta_kg' => $valorFrutaKg,
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
