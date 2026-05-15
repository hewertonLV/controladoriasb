<?php

namespace App\Http\Requests\Admin;

use App\Enums\FrutaUmIcms;
use App\Enums\FrutaUnidadeMedicao;
use App\Support\TextoCadastro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFrutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id_cigam' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                Rule::unique('frutas', 'id_cigam'),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'unidade_medicao' => ['required', 'string', Rule::in(FrutaUnidadeMedicao::values())],
            'kg_por_unidade_medicao' => ['required', 'numeric', 'min:0'],
            'icms_ex_compra' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'icms_na_compra' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'um_icms' => ['required', 'string', Rule::in(FrutaUmIcms::values())],
            'icms_venda' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_cigam' => 'ID CIGAM',
            'nome' => 'nome',
            'unidade_medicao' => 'unidade de medição',
            'kg_por_unidade_medicao' => 'kg por unidade de medição',
            'icms_ex_compra' => 'ICMS externo na compra',
            'icms_na_compra' => 'ICMS nacional na compra',
            'um_icms' => 'unidade de medida do ICMS',
            'icms_venda' => 'ICMS na venda (%)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_cigam.regex' => 'O ID CIGAM deve conter no máximo 6 dígitos numéricos (zeros à esquerda são aplicados automaticamente).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) $this->input('id_cigam', ''));

        $this->merge([
            'id_cigam' => $idCigam,
            'nome' => trim((string) $this->input('nome')),
            'unidade_medicao' => mb_strtoupper(trim((string) $this->input('unidade_medicao', '')), 'UTF-8'),
            'kg_por_unidade_medicao' => $this->input('kg_por_unidade_medicao', 0),
            'icms_ex_compra' => TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('icms_ex_compra', 0)),
            'icms_na_compra' => TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('icms_na_compra', 0)),
            'um_icms' => TextoCadastro::normalizarMaiusculas((string) $this->input('um_icms', '')),
            'icms_venda' => TextoCadastro::normalizarValorMonetarioBrasileiro($this->input('icms_venda', 0)),
        ]);
    }
}
