<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaptacaoOrdemCarregamentoRequest extends FormRequest
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
            'ordem_carregamento' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
