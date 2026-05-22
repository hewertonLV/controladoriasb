<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\TextoCadastro;
use Closure;
use Illuminate\Validation\Rule;

trait ValidatesUnidadeNegocioAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function unidadeNegocioRules(?int $ignoreId = null): array
    {
        $uniqueIdCigam = Rule::unique('unidades_negocio', 'id_cigam');
        if ($ignoreId !== null) {
            $uniqueIdCigam = $uniqueIdCigam->ignore($ignoreId);
        }

        return [
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                $uniqueIdCigam,
            ],
            'id_estado' => ['required', 'integer', 'min:1', Rule::exists('estados', 'id')],
            'razao_social' => ['required', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => [
                'nullable',
                'string',
                $this->cpfCnpjTamanhoRule(),
            ],
            'custo_operacional' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'possui_estoque' => ['required', 'boolean'],
            'is_unidade_producao' => ['required', 'boolean'],
            'is_hub' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function unidadeNegocioAttributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'id_estado' => 'estado (ICMS)',
            'razao_social' => 'razão social',
            'nome' => 'nome',
            'cpf_cnpj' => 'CPF/CNPJ',
            'custo_operacional' => 'custo operacional',
            'possui_estoque' => 'controla estoque',
            'is_unidade_producao' => 'unidade de produção',
            'is_hub' => 'unidade HUB',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function unidadeNegocioMessages(): array
    {
        return [
            'id_cigam.regex' => 'O ID CIGAM deve conter no máximo 6 dígitos numéricos (zeros à esquerda são aplicados automaticamente).',
            'custo_operacional.min' => 'O custo operacional não pode ser negativo.',
        ];
    }

    protected function prepareUnidadeNegocioForValidation(): void
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', ''));
        $documento = TextoCadastro::somenteDigitos((string) $this->input('cpf_cnpj', ''));
        $custoBruto = $this->input('custo_operacional');
        $custoNormalizado = '0.00';

        if ($custoBruto !== null && $custoBruto !== '') {
            $custoNormalizado = TextoCadastro::normalizarValorMonetarioBrasileiro($custoBruto);
        }

        $this->merge([
            'id_cigam' => $idCigam,
            'id_estado' => (int) $this->input('id_estado', 0),
            'razao_social' => trim((string) $this->input('razao_social')),
            'nome' => trim((string) $this->input('nome')),
            'cpf_cnpj' => $documento === '' ? null : $documento,
            'custo_operacional' => $custoNormalizado,
            'possui_estoque' => $this->boolean('possui_estoque'),
            'is_unidade_producao' => $this->boolean('is_unidade_producao'),
            'is_hub' => $this->boolean('is_hub'),
        ]);
    }

    protected function cpfCnpjTamanhoRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $len = strlen((string) $value);

            if (! in_array($len, [11, 14], true)) {
                $fail('O CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).');
            }
        };
    }
}
