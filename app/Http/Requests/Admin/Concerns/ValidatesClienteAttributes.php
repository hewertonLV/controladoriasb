<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Praca;
use App\Support\TextoCadastro;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesClienteAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function clienteRules(?int $ignoreId = null): array
    {
        $uniqueIdCigam = function () use ($ignoreId): mixed {
            $rule = Rule::unique('clientes', 'id_cigam');
            if ($ignoreId !== null) {
                $rule = $rule->ignore($ignoreId);
            }

            return $rule;
        };

        return [
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\\d{6}$/',
                $uniqueIdCigam(),
            ],
            'razao_social' => ['required', 'string', 'max:255'],
            'fantasia' => ['nullable', 'string', 'max:255'],
            'cnpj_cpf' => [
                'required',
                'string',
                $this->cpfCnpjLengthRule(),
            ],
            'id_unidade_negocio' => ['required', 'integer', 'min:1', 'exists:unidades_negocio,id'],
            'id_praca' => [
                'required',
                'integer',
                'min:1',
                'exists:pracas,id',
                $this->pracaPertenceUnidadeRule(),
            ],
            'grupo_id' => ['nullable', 'integer', 'min:1', 'exists:grupos,id'],
            'desconto_nf' => [
                'required',
                'numeric',
                'min:0',
                'regex:/^\\d+(\\.\\d{1,2})?$/',
            ],
            'desconto_contrato' => [
                'required',
                'numeric',
                'min:0',
                'regex:/^\\d+(\\.\\d{1,2})?$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function clienteAttributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'razao_social' => 'razão social',
            'fantasia' => 'fantasia',
            'cnpj_cpf' => 'CPF/CNPJ',
            'id_unidade_negocio' => 'unidade de negócio',
            'id_praca' => 'praça',
            'grupo_id' => 'grupo',
            'desconto_nf' => 'desconto NF',
            'desconto_contrato' => 'desconto contrato',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function clienteMessages(): array
    {
        return [
            'desconto_nf.min' => 'O desconto não pode ser negativo.',
            'desconto_contrato.min' => 'O desconto não pode ser negativo.',
        ];
    }

    protected function prepareClienteForValidation(): void
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', ''));
        $documento = TextoCadastro::somenteDigitos((string) $this->input('cnpj_cpf', ''));
        $descontoNf = $this->input('desconto_nf');
        $descontoContrato = $this->input('desconto_contrato');
        $fantasia = preg_replace('/\s+/u', ' ', (string) $this->input('fantasia', '')) ?? '';

        $grupoId = $this->input('grupo_id');

        $this->merge([
            'id_cigam' => $idCigam,
            'razao_social' => trim((string) $this->input('razao_social')),
            'fantasia' => TextoCadastro::normalizarMaiusculasOuNulo($fantasia),
            'cnpj_cpf' => $documento,
            'id_unidade_negocio' => (int) $this->input('id_unidade_negocio'),
            'id_praca' => (int) $this->input('id_praca'),
            'grupo_id' => $grupoId === null || $grupoId === '' ? null : (int) $grupoId,
            'desconto_nf' => $descontoNf === null || $descontoNf === '' ? '0.00' : (string) $descontoNf,
            'desconto_contrato' => $descontoContrato === null || $descontoContrato === '' ? '0.00' : (string) $descontoContrato,
        ]);
    }

    protected function cpfCnpjLengthRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $len = strlen((string) $value);

            if (! in_array($len, [11, 14], true)) {
                $fail('O CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).');
            }
        };
    }

    protected function pracaPertenceUnidadeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $idPraca = (int) $value;
            $idUnidade = (int) $this->input('id_unidade_negocio');

            if ($idPraca < 1 || $idUnidade < 1) {
                return;
            }

            $valida = Praca::query()
                ->whereKey($idPraca)
                ->where('id_unidade_negocio', $idUnidade)
                ->exists();

            if (! $valida) {
                $fail('A praça selecionada não pertence à unidade de negócio informada.');
            }
        };
    }
}
