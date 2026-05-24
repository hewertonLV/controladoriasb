<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaptacaoLoteRequest extends FormRequest
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
            'id_unidade_negocio_faturamento' => ['required', 'integer', 'exists:unidades_negocio,id'],
            'id_unidade_negocio_galpao' => ['required', 'integer', 'exists:unidades_negocio,id'],
        ];
    }
}
