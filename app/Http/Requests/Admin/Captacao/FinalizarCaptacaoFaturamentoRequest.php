<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinalizarCaptacaoFaturamentoRequest extends FormRequest
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
                Rule::exists('unidades_negocio', 'id'),
            ],
            'id_captacao_lote' => ['nullable', 'integer', Rule::exists('captacao_lotes', 'id')],
        ];
    }
}
