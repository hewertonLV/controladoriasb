<?php

namespace App\Services\Fornecedores;

use App\Support\TextoCadastro;

/**
 * Layout fixo da planilha (linha 1 = cabeçalho):
 *   A → id_cigam
 *   B → razao_social
 *   C → fantasia
 *   D → cnpj_cpf
 *   E → estado (nome cadastrado, ex.: CEARA)
 */
class FornecedorPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row
     * @return array{
     *     dados: array{
     *         id_cigam: string,
     *         razao_social: string,
     *         fantasia: string|null,
     *         cnpj_cpf: string,
     *         estado_nome: string,
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
        $fantasia = $this->trimString($row[2] ?? null);
        $cnpjCpf = $this->onlyDigits($row[3] ?? null);
        $estadoNome = TextoCadastro::normalizarMaiusculas($this->trimString($row[4] ?? null));

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM (coluna A) é obrigatório.';
        }

        if ($razaoSocial === '') {
            $erros[] = 'Razão social (coluna B) é obrigatória.';
        }

        if ($cnpjCpf === '') {
            $erros[] = 'CPF/CNPJ (coluna D) é obrigatório.';
        } elseif (! in_array(strlen($cnpjCpf), [11, 14], true)) {
            $erros[] = 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
        }

        if ($estadoNome === '') {
            $erros[] = 'Estado (coluna E) é obrigatório: informe o nome do estado cadastrado (ex.: CEARA).';
        }

        return [
            'dados' => [
                'id_cigam' => $idCigam,
                'razao_social' => mb_strtoupper($razaoSocial),
                'fantasia' => $fantasia !== '' ? mb_strtoupper($fantasia) : null,
                'cnpj_cpf' => $cnpjCpf,
                'estado_nome' => $estadoNome,
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
}
