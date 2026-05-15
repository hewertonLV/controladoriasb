<?php

namespace App\Services\Clientes;

use App\Models\Grupo;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → id_cigam
 *   B → razao_social
 *   C → cnpj_cpf
 *   D → id_unidade_negocio
 *   E → desconto_nf
 *   F → desconto_contrato
 *   G → praca (nome)
 *   H → grupo (nome, opcional)
 */
class ClientePlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         id_cigam: string,
     *         razao_social: string,
     *         cnpj_cpf: string,
     *         id_unidade_negocio: int|null,
     *         desconto_nf: string,
     *         desconto_contrato: string,
     *         id_praca: int|null,
     *         grupo_id: int|null,
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
        $razaoSocial = $this->trimString($row[1] ?? null);
        $cnpjCpf = $this->onlyDigits($row[2] ?? null);
        $idUnidade = $this->parseInteger($row[3] ?? null);
        $descontoNf = $this->parseDesconto($row[4] ?? null);
        $descontoContrato = $this->parseDesconto($row[5] ?? null);
        $pracaNome = TextoCadastro::normalizarMaiusculas($this->trimString($row[6] ?? null));
        $grupoNome = TextoCadastro::normalizarMaiusculas($this->trimString($row[7] ?? null));

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM (coluna A) é obrigatório.';
        }

        if ($razaoSocial === '') {
            $erros[] = 'Razão social (coluna B) é obrigatória.';
        }

        if ($cnpjCpf === '') {
            $erros[] = 'CPF/CNPJ (coluna C) é obrigatório.';
        } elseif (! in_array(strlen($cnpjCpf), [11, 14], true)) {
            $erros[] = 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
        }

        if ($idUnidade === null || $idUnidade < 1) {
            $erros[] = 'Unidade de negócio (coluna D) deve ser um inteiro maior ou igual a 1.';
        } elseif (! UnidadeNegocio::query()->whereKey($idUnidade)->exists()) {
            $erros[] = "Unidade de negócio {$idUnidade} não existe no cadastro.";
        }

        if ($descontoNf === null) {
            $erros[] = 'Desconto NF (coluna E) inválido. Informe um valor numérico maior ou igual a zero.';
        }

        if ($descontoContrato === null) {
            $erros[] = 'Desconto contrato (coluna F) inválido. Informe um valor numérico maior ou igual a zero.';
        }

        $idPraca = null;
        if ($pracaNome === '') {
            $erros[] = 'Praça (coluna G) é obrigatória.';
        } elseif ($idUnidade !== null && $idUnidade >= 1) {
            $praca = Praca::query()
                ->where('nome', $pracaNome)
                ->where('id_unidade_negocio', $idUnidade)
                ->first();

            if ($praca === null) {
                $erros[] = "Praça \"{$pracaNome}\" não encontrada para a unidade de negócio {$idUnidade}.";
            } else {
                $idPraca = $praca->id;
            }
        }

        $grupoId = null;
        if ($grupoNome !== '') {
            $grupo = Grupo::query()->where('nome', $grupoNome)->first();
            if ($grupo === null) {
                $erros[] = "Grupo \"{$grupoNome}\" não existe no cadastro.";
            } else {
                $grupoId = $grupo->id;
            }
        }

        return [
            'dados' => [
                'id_cigam' => $idCigam,
                'razao_social' => mb_strtoupper($razaoSocial),
                'cnpj_cpf' => $cnpjCpf,
                'id_unidade_negocio' => $idUnidade,
                'desconto_nf' => $descontoNf ?? '0.00',
                'desconto_contrato' => $descontoContrato ?? '0.00',
                'id_praca' => $idPraca,
                'grupo_id' => $grupoId,
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

    private function onlyDigits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private function parseInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return (int) $digits;
    }

    private function parseDesconto(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        if (! is_numeric($value)) {
            return null;
        }

        $num = (float) $value;
        if ($num < 0) {
            return null;
        }

        return number_format($num, 2, '.', '');
    }
}
