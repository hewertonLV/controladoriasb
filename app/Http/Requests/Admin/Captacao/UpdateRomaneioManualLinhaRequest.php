<?php

namespace App\Http\Requests\Admin\Captacao;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRomaneioManualLinhaRequest extends FormRequest
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
            'quantidade' => ['required_without:incremento', 'nullable', 'numeric', 'min:0'],
            'incremento' => ['required_without:quantidade', 'nullable', 'numeric'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
