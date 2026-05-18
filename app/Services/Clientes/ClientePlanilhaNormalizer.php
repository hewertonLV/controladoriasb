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
 *   D → id_cigam da unidade de negócio
 *   E → desconto_nf
 *   F → praca (nome)
 *   G → grupo (nome, opcional)
 *   H → fantasia/nome fantasia/fantasia_cliente (opcional quando detectado por cabeçalho)
 */
class ClientePlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         id_cigam: string,
     *         razao_social: string,
     *         fantasia: string|null,
     *         cnpj_cpf: string,
     *         id_unidade_negocio: int|null,
     *         id_cigam_unidade: string,
     *         desconto_nf: string,
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
        $fantasia = $this->normalizarTextoOpcional($row[7] ?? null);
        $cnpjCpf = $this->onlyDigits($row[2] ?? null);
        $unidadeOriginal = $this->trimString($row[3] ?? null);
        $idCigamUnidade = TextoCadastro::normalizarIdCigam($unidadeOriginal);
        $idUnidade = null;
        $descontoNf = $this->parseDesconto($row[4] ?? null);
        $pracaNome = TextoCadastro::normalizarMaiusculas($this->trimString($row[5] ?? null));
        $grupoNome = TextoCadastro::normalizarMaiusculas($this->trimString($row[6] ?? null));

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM (coluna A) é obrigatório.';
        }

        if ($razaoSocial === '') {
            $erros[] = 'Razão social (coluna B) é obrigatória.';
        }

        if ($fantasia !== null && mb_strlen($fantasia) > 255) {
            $erros[] = 'Fantasia pode ter no máximo 255 caracteres.';
        }

        if ($cnpjCpf === '') {
            $erros[] = 'CPF/CNPJ (coluna C) é obrigatório.';
        } elseif (! in_array(strlen($cnpjCpf), [11, 14], true)) {
            $erros[] = 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
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

        if ($descontoNf === null) {
            $erros[] = 'Desconto NF (coluna E) inválido. Informe um valor numérico maior ou igual a zero.';
        }

        $idPraca = null;
        if ($pracaNome === '') {
            $erros[] = 'Praça (coluna F) é obrigatória.';
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
                'fantasia' => $fantasia,
                'cnpj_cpf' => $cnpjCpf,
                'id_unidade_negocio' => $idUnidade,
                'id_cigam_unidade' => $idCigamUnidade,
                'desconto_nf' => $descontoNf ?? '0.00',
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

    private function normalizarTextoOpcional(mixed $value): ?string
    {
        $texto = preg_replace('/\s+/u', ' ', $this->trimString($value)) ?? '';

        return $texto === '' ? null : mb_strtoupper($texto);
    }

    private function onlyDigits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
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

    private function mensagemUnidadeNaoEncontrada(string $normalizado, string $original): string
    {
        return "Unidade de negócio com id_cigam {$normalizado} não encontrada. Valor original informado: {$original}.";
    }
}
