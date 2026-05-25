<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaptacaoRotaRequest extends FormRequest
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
            'id_captacao_carteira' => [
                'required',
                'integer',
                Rule::exists('captacao_carteiras', 'id')->where('ativo', true),
            ],
            'nome' => ['required', 'string', 'max:120'],
            'id_veiculo' => ['nullable', 'integer', 'exists:veiculos,id'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
