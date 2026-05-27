<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdicionarRomaneioManualFrutaRequest extends FormRequest
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
            'id_fruta' => ['required', 'integer', 'exists:frutas,id'],
            'id_unidade_origem_fisica' => [
                'required',
                'integer',
                Rule::exists('unidades_negocio', 'id')->where('is_hub', true),
            ],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
