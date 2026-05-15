<?php

namespace App\Services\Empresas;

use App\Models\Empresa;

/**
 * Normaliza uma linha bruta da planilha de empresas (colunas A..G por posição)
 * em um array de dados consumíveis pelo módulo de Empresas, juntamente com
 * eventuais erros de parsing/validação encontrados durante a leitura.
 *
 * Layout fixo da planilha (sem depender do texto do cabeçalho):
 *   A → Ativo            → status            (boolean)
 *   B → Empresa          → id_cigam          (string)
 *   C → Nome completo    → nome              (string)
 *   D → Fantasia         → fantasia          (string|null)
 *   E → CNPJ/CPF         → cpf_cnpj          (string, somente dígitos)
 *   F → Unidade negócio  → unidade_negocio   (int)
 *   G → Pessoa           → tipo_pessoa       (FISICA|JURIDICA)
 */
class EmpresaPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $row  Linha sequencial (índice 0=A, 1=B, ...)
     * @return array{
     *     dados: array{
     *         status: bool,
     *         id_cigam: string,
     *         nome: string,
     *         fantasia: string|null,
     *         cpf_cnpj: string,
     *         unidade_negocio: int|null,
     *         tipo_pessoa: string,
     *     },
     *     erros: list<string>,
     * }
     */
    public function normalize(array $row): array
    {
        $erros = [];

        $status = $this->parseStatus($row[0] ?? null);
        $idCigam = $this->trimString($row[1] ?? null);
        $nome = $this->trimString($row[2] ?? null);
        $fantasia = $this->trimString($row[3] ?? null);
        $cpfCnpj = $this->onlyDigits($row[4] ?? null);
        $unidadeNegocio = $this->parseInteger($row[5] ?? null);
        $tipoPessoa = $this->parseTipoPessoa($row[6] ?? null);

        if ($idCigam === '') {
            $erros[] = 'ID CIGAM (coluna B) é obrigatório.';
        }

        if ($nome === '') {
            $erros[] = 'Nome (coluna C) é obrigatório.';
        }

        if ($tipoPessoa === null) {
            $erros[] = 'Tipo de pessoa (coluna G) inválido. Use F/FISICA/PF/PESSOA FISICA ou J/JURIDICA/PJ/PESSOA JURIDICA.';
        }

        if ($cpfCnpj === '') {
            $erros[] = 'CPF/CNPJ (coluna E) é obrigatório.';
        } elseif ($tipoPessoa === Empresa::TIPO_PESSOA_FISICA && strlen($cpfCnpj) !== 11) {
            $erros[] = 'Para pessoa física, o CPF deve ter 11 dígitos.';
        } elseif ($tipoPessoa === Empresa::TIPO_PESSOA_JURIDICA && strlen($cpfCnpj) !== 14) {
            $erros[] = 'Para pessoa jurídica, o CNPJ deve ter 14 dígitos.';
        }

        if ($unidadeNegocio === null || $unidadeNegocio < 1) {
            $erros[] = 'Unidade de negócio (coluna F) deve ser um inteiro maior ou igual a 1.';
        }

        return [
            'dados' => [
                'status' => $status,
                'id_cigam' => $idCigam,
                'nome' => $nome,
                'fantasia' => $fantasia !== '' ? $fantasia : null,
                'cpf_cnpj' => $cpfCnpj,
                'unidade_negocio' => $unidadeNegocio,
                'tipo_pessoa' => $tipoPessoa ?? '',
            ],
            'erros' => $erros,
        ];
    }

    private function parseStatus(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtoupper(trim((string) $value));
        $normalized = str_replace(['Ã', 'Á', 'Â', 'À'], 'A', $normalized);

        return match ($normalized) {
            'SIM', 'S', '1', 'TRUE', 'V' => true,
            'NAO', 'N', '0', 'FALSE', 'F' => false,
            default => true,
        };
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

    private function parseTipoPessoa(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim((string) $value));
        $normalized = str_replace(['Í', 'Ì', 'Î'], 'I', $normalized);
        $normalized = str_replace(['Ú', 'Ù', 'Û'], 'U', $normalized);
        $normalized = str_replace(['Ã', 'Á', 'Â', 'À'], 'A', $normalized);

        return match ($normalized) {
            'F', 'PF', 'FISICA', 'PESSOA FISICA' => Empresa::TIPO_PESSOA_FISICA,
            'J', 'PJ', 'JURIDICA', 'PESSOA JURIDICA' => Empresa::TIPO_PESSOA_JURIDICA,
            default => null,
        };
    }
}
