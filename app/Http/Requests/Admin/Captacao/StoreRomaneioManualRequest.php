<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRomaneioManualRequest extends FormRequest
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
            'data_referencia' => ['required', 'date'],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_unidade_negocio_faturamento.exists' => 'Selecione uma unidade de faturamento que emite nota fiscal (usada na exportação Cigam).',
            'id_unidade_negocio_galpao.exists' => 'Selecione um galpão operacional de destino.',
        ];
    }
}
