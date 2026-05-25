<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'id_captacao_carteira' => [
                'required',
                'integer',
                Rule::exists('captacao_carteiras', 'id')->where('ativo', true),
            ],
        ];
    }
}
