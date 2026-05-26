<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Cliente;
use App\Support\TextoCadastro;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesUnidadeNegocioAttributes
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function unidadeNegocioRules(?int $ignoreUnidadeId = null): array
    {
        $uniqueIdCigam = Rule::unique('unidades_negocio', 'id_cigam');
        $uniqueIdCliente = Rule::unique('unidades_negocio', 'id_cliente');
        if ($ignoreUnidadeId !== null) {
            $uniqueIdCigam = $uniqueIdCigam->ignore($ignoreUnidadeId);
            $uniqueIdCliente = $uniqueIdCliente->ignore($ignoreUnidadeId);
        }

        return [
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                $uniqueIdCigam,
            ],
            'centro_armazenagem' => ['required', 'string', 'regex:/^\d{1,3}$/'],
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
            'is_galpao_operacional' => ['required', 'boolean'],
            'emite_nota_fiscal' => ['required', 'boolean'],
            'id_cliente' => ['nullable', 'integer', Rule::exists('clientes', 'id'), $uniqueIdCliente],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function unidadeNegocioAttributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'centro_armazenagem' => 'centro de armazenagem',
            'id_estado' => 'estado (ICMS)',
            'razao_social' => 'razão social',
            'nome' => 'nome',
            'cpf_cnpj' => 'CPF/CNPJ',
            'custo_operacional' => 'custo operacional',
            'possui_estoque' => 'controla estoque',
            'is_unidade_producao' => 'unidade de produção',
            'is_hub' => 'unidade HUB',
            'is_galpao_operacional' => 'galpão operacional',
            'emite_nota_fiscal' => 'emite nota fiscal',
            'id_cliente' => 'código do cliente',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function unidadeNegocioMessages(): array
    {
        return [
            'id_cigam.regex' => 'O ID CIGAM deve conter no máximo 6 dígitos numéricos (zeros à esquerda são aplicados automaticamente).',
            'centro_armazenagem.regex' => 'O centro de armazenagem deve ter de 1 a 3 dígitos numéricos (ex.: 001).',
            'custo_operacional.min' => 'O custo operacional não pode ser negativo.',
        ];
    }

    protected function prepareUnidadeNegocioForValidation(): void
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', ''));
        $centroArmazenagem = TextoCadastro::somenteDigitos((string) $this->input('centro_armazenagem', '001'));
        $documento = TextoCadastro::somenteDigitos((string) $this->input('cpf_cnpj', ''));
        $custoBruto = $this->input('custo_operacional');
        $custoNormalizado = '0.00';

        if ($custoBruto !== null && $custoBruto !== '') {
            $custoNormalizado = TextoCadastro::normalizarValorMonetarioBrasileiro($custoBruto);
        }

        $this->merge([
            'id_cigam' => $idCigam,
            'centro_armazenagem' => str_pad(
                substr($centroArmazenagem === '' ? '001' : $centroArmazenagem, 0, 3),
                3,
                '0',
                STR_PAD_LEFT,
            ),
            'id_estado' => (int) $this->input('id_estado', 0),
            'razao_social' => trim((string) $this->input('razao_social')),
            'nome' => trim((string) $this->input('nome')),
            'cpf_cnpj' => $documento === '' ? null : $documento,
            'custo_operacional' => $custoNormalizado,
            'possui_estoque' => $this->boolean('possui_estoque'),
            'is_unidade_producao' => $this->boolean('is_unidade_producao'),
            'is_hub' => $this->boolean('is_hub'),
            'is_galpao_operacional' => $this->boolean('is_galpao_operacional'),
            'emite_nota_fiscal' => $this->boolean('emite_nota_fiscal'),
            'id_cliente' => $this->filled('id_cliente') ? (int) $this->input('id_cliente') : null,
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

    protected function validarCombinacaoFlagsUnidadeNegocio(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $isGalpao = $this->boolean('is_galpao_operacional');
            $isHub = $this->boolean('is_hub');

            if ($isGalpao && ! $this->boolean('possui_estoque')) {
                $v->errors()->add('is_galpao_operacional', 'Galpão operacional deve controlar estoque.');
            }

            if ($isHub && $this->boolean('emite_nota_fiscal')) {
                $v->errors()->add('emite_nota_fiscal', 'Unidade HUB não emite nota fiscal.');
            }
        });
    }

    protected function validarClienteDaUnidadeNegocio(Validator $validator, ?int $unidadeNegocioId): void
    {
        $validator->after(function (Validator $v) use ($unidadeNegocioId): void {
            $idCliente = $this->input('id_cliente');
            if ($idCliente === null || $idCliente === '') {
                return;
            }

            if ($unidadeNegocioId === null) {
                $v->errors()->add(
                    'id_cliente',
                    'Salve a unidade de negócio antes de vincular o código do cliente.',
                );

                return;
            }

            $cliente = Cliente::query()->find((int) $idCliente);
            if ($cliente === null) {
                return;
            }

            if ((int) $cliente->id_unidade_negocio !== $unidadeNegocioId) {
                $v->errors()->add(
                    'id_cliente',
                    'O cliente selecionado não pertence a esta unidade de negócio.',
                );
            }
        });
    }
}
