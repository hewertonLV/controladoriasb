<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaptacaoCarteiraRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:120'],
            'id_unidade_negocio_faturamento' => [
                'required',
                'integer',
                Rule::exists('unidades_negocio', 'id')->where('emite_nota_fiscal', true),
            ],
            'id_unidade_negocio_galpao' => [
                'required',
                'integer',
                Rule::exists('unidades_negocio', 'id')->where('is_galpao_operacional', true),
            ],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
